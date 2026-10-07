<?php
/**
 * Client IP resolution behind CDNs and reverse proxies.
 *
 * Has no WordPress dependencies: it is also used by the SHORTINIT search
 * endpoint.
 *
 * Forwarding headers are only trusted when the direct peer is a known proxy:
 * a Cloudflare edge (CF-Connecting-IP), or a private/loopback address or an
 * IP listed in the WSS_TRUSTED_PROXIES constant (X-Forwarded-For / X-Real-IP).
 * A visitor connecting directly can therefore not spoof their IP, while sites
 * behind Cloudflare or a load balancer no longer see every visitor as the same
 * IP (which made all of them share one rate-limit bucket).
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Client_IP
 */
class WSS_Client_IP {

	/**
	 * Cloudflare edge ranges (https://www.cloudflare.com/ips/).
	 */
	const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Resolved IP for this request.
	 *
	 * @var string|null
	 */
	private static $ip = null;

	/**
	 * Get the visitor's IP address.
	 *
	 * @return string
	 */
	public static function get(): string {
		if ( null !== self::$ip ) {
			return self::$ip;
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! self::is_valid( $remote ) ) {
			$remote = '127.0.0.1';
		}

		$ip = $remote;

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && self::in_ranges( $remote, self::CLOUDFLARE_RANGES ) ) {
			$cf = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( self::is_valid( $cf ) ) {
				$ip = $cf;
			}
		} elseif ( self::is_trusted_proxy( $remote ) ) {
			$ip = self::from_forwarded_headers( $remote );
		}

		self::$ip = $ip;
		return $ip;
	}

	/**
	 * Key to group requests for rate limiting. IPv6 visitors get a whole /64
	 * (one subscriber line), otherwise rotating addresses bypass the limit.
	 *
	 * @param string|null $ip IP (defaults to the current visitor).
	 * @return string
	 */
	public static function rate_key( $ip = null ): string {
		$ip = null === $ip ? self::get() : $ip;
		if ( false !== strpos( $ip, ':' ) ) {
			$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $packed && 16 === strlen( $packed ) ) {
				return bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
			}
		}
		return $ip;
	}

	/**
	 * Walk X-Forwarded-For from the right (the entry appended by our own
	 * proxy) and return the first address that is not a trusted proxy.
	 *
	 * @param string $remote Direct peer.
	 * @return string
	 */
	private static function from_forwarded_headers( string $remote ): string {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$chain = array_reverse( array_map( 'trim', explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( $chain as $candidate ) {
				if ( ! self::is_valid( $candidate ) ) {
					break; // Malformed entry: stop trusting the rest of the chain.
				}
				if ( ! self::is_trusted_proxy( $candidate ) ) {
					return $candidate;
				}
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$real = trim( (string) $_SERVER['HTTP_X_REAL_IP'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( self::is_valid( $real ) ) {
				return $real;
			}
		}

		return $remote;
	}

	/**
	 * Whether an address is a proxy we accept forwarding headers from.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	private static function is_trusted_proxy( string $ip ): bool {
		// Private / loopback / reserved: same host or internal network.
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}

		if ( defined( 'WSS_TRUSTED_PROXIES' ) ) {
			$list = WSS_TRUSTED_PROXIES;
			if ( is_string( $list ) ) {
				$list = array_map( 'trim', explode( ',', $list ) );
			}
			if ( is_array( $list ) && self::in_ranges( $ip, $list ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an IP falls in any of the given addresses / CIDR ranges.
	 *
	 * @param string $ip     IP.
	 * @param array  $ranges Addresses or CIDRs.
	 * @return bool
	 */
	private static function in_ranges( string $ip, array $ranges ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $packed ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			$range = (string) $range;
			$parts = explode( '/', $range, 2 );
			$net   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $net || strlen( $net ) !== strlen( $packed ) ) {
				continue;
			}
			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $net ) * 8;
			$full = intdiv( $bits, 8 );
			if ( substr( $packed, 0, $full ) !== substr( $net, 0, $full ) ) {
				continue;
			}
			$rem = $bits % 8;
			if ( 0 === $rem ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $rem ) ) & 0xff;
			if ( ( ord( $packed[ $full ] ) & $mask ) === ( ord( $net[ $full ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate an IP address.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	private static function is_valid( string $ip ): bool {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}
}

# CLAUDE.md — Woo Smart Search

Guía para cualquier sesión de Claude Code que trabaje en este repositorio.
Plugin de búsqueda para WordPress/WooCommerce. El código del plugin vive en `woo-smart-search/`.

## Reglas obligatorias en cada cambio
1. **Desarrollar en la rama** `claude/analyze-wordpress-search-plugin-TeNVh`.
2. **Subir versión SIEMPRE.** En `woo-smart-search/woo-smart-search.php` actualizar el header `Version:` y la constante `WSS_VERSION`, y añadir una entrada en `readme.txt` bajo `== Changelog ==`.
3. **Publicar el ZIP en la rama `release`** tras cada cambio: sincronizar la carpeta `woo-smart-search/` y regenerar `woo-smart-search.zip` excluyendo `*/node_modules/*`, `*/tests/*` y `*.map`.
4. **No crear Pull Requests** salvo petición explícita.
5. **No incluir ningún identificador del modelo de IA** en commits, PRs, comentarios de código ni artefactos subidos (solo en el chat).
6. Footers de commit:
   - `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
   - `Claude-Session: https://claude.ai/code/session_011HcCKfE5EpsjrfEjJyV3Mo`
7. **Firmar todos los commits.** Antes de commitear, asegurar que la firma SSH está activa para que salgan como *Verified* en GitHub:
   ```
   git config commit.gpgsign true
   git config gpg.format ssh
   git config user.name Claude
   git config user.email noreply@anthropic.com
   git config user.signingkey /home/claude/.ssh/commit_signing_key.pub
   git config gpg.ssh.program /tmp/code-sign
   ```
   Si un commit quedó `Unverified`, re-firmarlo con `git commit --amend --no-edit --reset-author` (o rebase) y `git push --force-with-lease`. Comprobar con `git cat-file commit HEAD | grep 'BEGIN SSH SIGNATURE'` (el entorno no trae `ssh-keygen`, así que `git log %G?` no da `G` localmente; la verificación real se ve en la UI de GitHub).

## Build del panel de administración
El admin es **Vue 3 + Element Plus + Vite** en `admin-app/`. Si editas archivos `.vue` o `admin-app/src/**`, reconstruir:
```
cd admin-app && npm run build   # salida a assets/admin-app/
```
Los cambios de solo PHP/JS de frontend no requieren build.

## Arquitectura (dónde está cada cosa)
- **Motores de búsqueda**
  - Meilisearch: `includes/class-wss-meilisearch.php` (HTTP REST).
  - Motor local: `includes/class-wss-local-engine.php` (índice invertido MySQL, TF-IDF). Hace match por palabra completa o **prefijo**, no por subcadena.
- **Sincronización** (`includes/sync/`)
  - `class-wss-product-sync.php`: hooks de guardado/stock/REST/importadores + reindexado periódico de seguridad.
  - `class-wss-sync-queue.php`: cola con Action Scheduler + WP-Cron + **procesado al cierre de la petición** (`shutdown` + `fastcgi_finish_request`).
  - `class-wss-product-transformer.php`: construye el documento indexado, incl. el campo `search_codes` (subcadenas de SKUs/códigos para búsqueda por fragmentos).
- **Frontend**: `includes/frontend/`, plantillas en `templates/` (`search-widget.php`, `results-page.php`), JS en `assets/js/` (`search-widget.js`, `results-page.js`).

## Convenciones de layouts
- Widget: 6 layouts (standard/compact/amazon = "familia dropdown" con mismo HTML; expanded/falabella/fullscreen cambian el HTML). Existe layout de móvil separado (`widget_layout_mobile`): swap de clase si ambos son dropdown, doble render si difiere la estructura.
- Página de resultados: layout de móvil separado (`results_layout_mobile`) por swap de clase CSS.

## Notas de estado
- Versión actual: **6.19.0**.
- Tras la 6.19.0, hace falta **un reindexado completo una vez** para que los productos existentes generen `search_codes` (búsqueda por fragmentos "065", "0065", etc.).

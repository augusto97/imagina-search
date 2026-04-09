<template>
  <div>
    <div v-for="group in groups" :key="group.title" class="wss-section">
      <div class="wss-section-header"><div><h3>{{ group.title }}</h3></div></div>
      <div class="wss-section-body">
        <div v-for="field in group.fields" :key="field.key" class="wss-form-row">
          <div class="wss-form-label">{{ field.label }}</div>
          <div class="wss-form-control">
            <el-input
              v-model="translations[field.key]"
              :placeholder="field.placeholder"
            />
          </div>
        </div>
      </div>
    </div>

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">Save Translations</el-button>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { ElMessage } from 'element-plus';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();

const translations = reactive(settings.translations || {});

const groups = [
  {
    title: 'Search Widget',
    fields: [
      { key: 'placeholder', label: 'Placeholder', placeholder: 'Search products...' },
      { key: 'noResults', label: 'No results', placeholder: 'No results found for' },
      { key: 'viewAll', label: 'View all (count)', placeholder: 'View all %d results' },
      { key: 'viewAllResults', label: 'View all', placeholder: 'View all results' },
      { key: 'error', label: 'Error', placeholder: 'Connection error, please try again' },
      { key: 'startTyping', label: 'Start typing', placeholder: 'Start typing to search...' },
    ],
  },
  {
    title: 'Section Headers',
    fields: [
      { key: 'products', label: 'Products', placeholder: 'Products' },
      { key: 'results', label: 'Results', placeholder: 'Results' },
      { key: 'content', label: 'Content', placeholder: 'Content' },
      { key: 'categories', label: 'Categories', placeholder: 'Categories' },
      { key: 'popularSearches', label: 'Popular Searches', placeholder: 'Popular' },
      { key: 'suggestions', label: 'Suggestions', placeholder: 'Suggestions' },
    ],
  },
  {
    title: 'Product Details',
    fields: [
      { key: 'inStock', label: 'In stock', placeholder: 'In stock' },
      { key: 'outOfStock', label: 'Out of stock', placeholder: 'Out of stock' },
      { key: 'onBackorder', label: 'On backorder', placeholder: 'On backorder' },
      { key: 'addToCart', label: 'Add to Cart', placeholder: 'Add to Cart' },
      { key: 'freeShipping', label: 'Free shipping', placeholder: 'Free shipping' },
      { key: 'sold', label: 'Sold', placeholder: 'sold' },
    ],
  },
  {
    title: 'Facets & Filters',
    fields: [
      { key: 'tags', label: 'Tags', placeholder: 'Tags' },
      { key: 'stock', label: 'Stock', placeholder: 'Stock' },
      { key: 'brand', label: 'Brand', placeholder: 'Brand' },
      { key: 'rating', label: 'Rating', placeholder: 'Rating' },
      { key: 'price', label: 'Price', placeholder: 'Price' },
      { key: 'priceMin', label: 'Price min', placeholder: 'Min' },
      { key: 'priceMax', label: 'Price max', placeholder: 'Max' },
      { key: 'contentType', label: 'Content Type', placeholder: 'Content Type' },
      { key: 'author', label: 'Author', placeholder: 'Author' },
      { key: 'clearAll', label: 'Clear all', placeholder: 'Clear all' },
    ],
  },
  {
    title: 'Results Page',
    fields: [
      { key: 'resultsFor', label: 'Results for', placeholder: 'Results for "%s"' },
      { key: 'xResults', label: 'Results count', placeholder: '%d results' },
      { key: 'xProducts', label: 'Products count', placeholder: '%d products' },
      { key: 'noResultsPage', label: 'No results', placeholder: 'No results found matching your search.' },
      { key: 'errorLoading', label: 'Error loading', placeholder: 'Error loading results. Please try again.' },
      { key: 'filters', label: 'Filters button', placeholder: 'Filters' },
    ],
  },
  {
    title: 'Sort Options',
    fields: [
      { key: 'sortRelevance', label: 'Relevance', placeholder: 'Relevance' },
      { key: 'sortPriceLow', label: 'Price low', placeholder: 'Price: Low to High' },
      { key: 'sortPriceHigh', label: 'Price high', placeholder: 'Price: High to Low' },
      { key: 'sortNewest', label: 'Newest', placeholder: 'Newest' },
      { key: 'sortPopular', label: 'Popular', placeholder: 'Most Popular' },
      { key: 'sortRating', label: 'Rating', placeholder: 'Best Rated' },
      { key: 'sortNameAZ', label: 'Name A-Z', placeholder: 'Name: A–Z' },
      { key: 'sortNameZA', label: 'Name Z-A', placeholder: 'Name: Z–A' },
    ],
  },
  {
    title: 'Fullscreen Layout',
    fields: [
      { key: 'searchOurStore', label: 'Search title', placeholder: 'Search our store' },
      { key: 'collections', label: 'Collections', placeholder: 'Collections' },
      { key: 'brands', label: 'Brands', placeholder: 'Brands' },
      { key: 'relatedBrands', label: 'Related Brands', placeholder: 'Related Brands' },
      { key: 'relatedCategories', label: 'Related Categories', placeholder: 'Related Categories' },
    ],
  },
];

async function handleSave() {
  try {
    settings.translations = { ...translations };
    const msg = await save('translations');
    ElMessage.success(msg);
  } catch (e) {
    ElMessage.error(e.message);
  }
}
</script>

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

    <el-button type="primary" :loading="saving" @click="handleSave" size="large">{{ t('Save Translations') }}</el-button>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { ElMessage } from 'element-plus';
import { t } from '@/i18n';
import { useSettings } from '@/composables/useSettings';

const { settings, saving, save } = useSettings();

const translations = reactive(settings.translations || {});

const groups = [
  {
    title: t('Search Widget'),
    fields: [
      { key: 'placeholder', label: t('Placeholder'), placeholder: t('Search products...') },
      { key: 'noResults', label: t('No results'), placeholder: t('No results found for') },
      { key: 'viewAll', label: t('View all (count)'), placeholder: t('View all %d results') },
      { key: 'viewAllResults', label: t('View all'), placeholder: t('View all results') },
      { key: 'error', label: t('Error'), placeholder: t('Connection error, please try again') },
      { key: 'startTyping', label: t('Start typing'), placeholder: t('Start typing to search...') },
    ],
  },
  {
    title: t('Section Headers'),
    fields: [
      { key: 'products', label: t('Products'), placeholder: t('Products') },
      { key: 'results', label: t('Results'), placeholder: t('Results') },
      { key: 'content', label: t('Content'), placeholder: t('Content') },
      { key: 'categories', label: t('Categories'), placeholder: t('Categories') },
      { key: 'popularSearches', label: t('Popular Searches'), placeholder: t('Popular') },
      { key: 'suggestions', label: t('Suggestions'), placeholder: t('Suggestions') },
    ],
  },
  {
    title: t('Product Details'),
    fields: [
      { key: 'inStock', label: t('In stock'), placeholder: t('In stock') },
      { key: 'outOfStock', label: t('Out of stock'), placeholder: t('Out of stock') },
      { key: 'onBackorder', label: t('On backorder'), placeholder: t('On backorder') },
      { key: 'addToCart', label: t('Add to Cart'), placeholder: t('Add to Cart') },
      { key: 'freeShipping', label: t('Free shipping'), placeholder: t('Free shipping') },
      { key: 'sold', label: t('Sold'), placeholder: t('sold') },
    ],
  },
  {
    title: t('Facets & Filters'),
    fields: [
      { key: 'tags', label: t('Tags'), placeholder: t('Tags') },
      { key: 'stock', label: t('Stock'), placeholder: t('Stock') },
      { key: 'brand', label: t('Brand'), placeholder: t('Brand') },
      { key: 'rating', label: t('Rating'), placeholder: t('Rating') },
      { key: 'price', label: t('Price'), placeholder: t('Price') },
      { key: 'priceMin', label: t('Price min'), placeholder: t('Min') },
      { key: 'priceMax', label: t('Price max'), placeholder: t('Max') },
      { key: 'contentType', label: t('Content Type'), placeholder: t('Content Type') },
      { key: 'author', label: t('Author'), placeholder: t('Author') },
      { key: 'clearAll', label: t('Clear all'), placeholder: t('Clear all') },
    ],
  },
  {
    title: t('Results Page'),
    fields: [
      { key: 'resultsFor', label: t('Results for'), placeholder: t('Results for "%s"') },
      { key: 'xResults', label: t('Results count'), placeholder: t('%d results') },
      { key: 'xProducts', label: t('Products count'), placeholder: t('%d products') },
      { key: 'noResultsPage', label: t('No results'), placeholder: t('No results found matching your search.') },
      { key: 'errorLoading', label: t('Error loading'), placeholder: t('Error loading results. Please try again.') },
      { key: 'filters', label: t('Filters button'), placeholder: t('Filters') },
    ],
  },
  {
    title: t('Sort Options'),
    fields: [
      { key: 'sortRelevance', label: t('Relevance'), placeholder: t('Relevance') },
      { key: 'sortPriceLow', label: t('Price low'), placeholder: t('Price: Low to High') },
      { key: 'sortPriceHigh', label: t('Price high'), placeholder: t('Price: High to Low') },
      { key: 'sortNewest', label: t('Newest'), placeholder: t('Newest') },
      { key: 'sortPopular', label: t('Popular'), placeholder: t('Most Popular') },
      { key: 'sortRating', label: t('Rating'), placeholder: t('Best Rated') },
      { key: 'sortNameAZ', label: t('Name A-Z'), placeholder: t('Name: A–Z') },
      { key: 'sortNameZA', label: t('Name Z-A'), placeholder: t('Name: Z–A') },
    ],
  },
  {
    title: t('Fullscreen Layout'),
    fields: [
      { key: 'searchOurStore', label: t('Search title'), placeholder: t('Search our store') },
      { key: 'collections', label: t('Collections'), placeholder: t('Collections') },
      { key: 'brands', label: t('Brands'), placeholder: t('Brands') },
      { key: 'relatedBrands', label: t('Related Brands'), placeholder: t('Related Brands') },
      { key: 'relatedCategories', label: t('Related Categories'), placeholder: t('Related Categories') },
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

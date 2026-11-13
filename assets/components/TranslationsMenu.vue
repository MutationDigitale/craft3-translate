<template>
  <div>
    <nav>
      <ul>
        <li v-for="cat in categories" v-bind:key="cat">
          <a :href="categoryUrl(cat)" :class="{'sel': cat === category}"
             :aria-current="cat === category ? 'page' : null"
             @click="onCategoryClick($event, cat)">
            {{ cat }}
          </a>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useCraft } from '../helpers';

defineProps({
  categories: Array,
});

const store = useStore();
const { craft, t } = useCraft();

const category = computed(() => store.state.category);

function categoryUrl (cat) {
  return craft.getCpUrl(`translations-admin/${encodeURIComponent(cat)}`);
}

function pushCategoryState (cat) {
  window.history.pushState({ translationsCategory: cat }, '', categoryUrl(cat));
}

function confirmLeave () {
  return !store.getters.isModified ||
    confirm(t('You have unsaved changes. Are you sure you want to leave?'));
}

function changeCategory (cat, pushState = true) {
  store.commit('setCategory', cat);
  if (pushState) {
    pushCategoryState(cat);
  }
}

function onCategoryClick (event, cat) {
  // Opened in a new tab or window by the browser
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) {
    return;
  }
  event.preventDefault();
  if (cat !== category.value && confirmLeave()) {
    changeCategory(cat);
  }
}

onMounted(() => {
  // The category is kept in the history state, the URL format depends on the site config (e.g. without clean URLs)
  window.history.replaceState({ ...window.history.state, translationsCategory: category.value }, '');

  window.addEventListener('popstate', (event) => {
    const cat = event.state?.translationsCategory;
    if (!cat) {
      return;
    }
    if (cat !== category.value && !confirmLeave()) {
      // Restore the URL of the current category
      pushCategoryState(category.value);
      return;
    }
    changeCategory(cat, false);
  });
});
</script>


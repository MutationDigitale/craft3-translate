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

<script>
import {mapGetters, mapMutations, mapState} from 'vuex';

export default {
  props: {
    categories: Array
  },
  computed: {
    ...mapState({
      category: state => state.category,
    }),
    ...mapGetters({
      isModified: 'isModified',
    })
  },
  mounted() {
    // The category is kept in the history state, the URL format depends on the site config (e.g. without clean URLs)
    window.history.replaceState({ ...window.history.state, translationsCategory: this.category }, '');

    window.addEventListener('popstate', (event) => {
      const cat = event.state?.translationsCategory;
      if (!cat) {
        return;
      }
      if (cat !== this.category && !this.confirmLeave()) {
        // Restore the URL of the current category
        this.pushCategoryState(this.category);
        return;
      }
      this.changeCategory(cat, false);
    });
  },
  methods: {
    categoryUrl(cat) {
      return this.$craft.getCpUrl(`translations-admin/${encodeURIComponent(cat)}`);
    },
    pushCategoryState(cat) {
      window.history.pushState({ translationsCategory: cat }, '', this.categoryUrl(cat));
    },
    confirmLeave() {
      return !this.isModified ||
        confirm(this.$craft.t('translations-admin', 'You have unsaved changes. Are you sure you want to leave?'));
    },
    onCategoryClick(event, cat) {
      // Opened in a new tab or window by the browser
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
      }
      event.preventDefault();
      if (cat !== this.category && this.confirmLeave()) {
        this.changeCategory(cat);
      }
    },
    changeCategory(cat, pushState = true) {
      this.setCategory(cat);
      if (pushState) {
        this.pushCategoryState(cat);
      }
    },
    ...mapMutations({
      setCategory: 'setCategory',
    })
  }
};
</script>


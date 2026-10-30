<template>
  <div>
    <nav>
      <ul>
        <li v-for="cat in categories" v-bind:key="cat">
          <a href="" :class="{'sel': cat === category}"
             @click.prevent="onCategoryClick(cat)">
            {{ cat }}
          </a>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script>
import {mapMutations, mapState} from 'vuex';

export default {
  props: {
    categories: Array
  },
  computed: {
    ...mapState({
      category: state => state.category,
      modifiedMessagesKeys: state => state.modifiedMessagesKeys,
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
    pushCategoryState(cat) {
      window.history.pushState({ translationsCategory: cat }, '', this.$craft.getCpUrl(`translations-admin/${encodeURIComponent(cat)}`));
    },
    confirmLeave() {
      return this.modifiedMessagesKeys.length === 0 ||
        confirm(this.$craft.t('translations-admin', 'You have unsaved changes. Are you sure you want to leave?'));
    },
    onCategoryClick(cat) {
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


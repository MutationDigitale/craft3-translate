<template>
    <div v-if="categories.length > 1">
        <a id="sidebar-toggle" @click="toggleSidebar()">
            <span id="selected-sidebar-item-label">{{ category }}</span>&nbsp;
            <span data-icon="downangle"></span>
        </a>
        <div id="sidebar" class="sidebar">
            <nav>
                <ul>
                    <li v-for="cat in categories" v-bind:key="cat">
                        <a href="" :class="{'sel': cat === category}"
                           @click.prevent="changeCategory(cat)">
                            {{ cat }}
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import { EventBus } from './../EventBus.js';

export default {
  props: {
    categories: Array,
    category: String
  },
  created () {
    window.addEventListener('popstate', () => {
      const pathSplit = document.location.pathname.split('/');
      if (pathSplit.length >= 3 && pathSplit[2] === 'translations-admin') {
        this.changeCategory(pathSplit.length >= 4 ? pathSplit[3] : this.categories[0], false);
      }
    });
  },
  mounted () {
    EventBus.$emit('category-changed', this.category);
  },
  methods: {
    changeCategory (cat, pushState = true) {
      this.category = cat;
      EventBus.$emit('category-changed', cat);
      if (pushState) {
        window.history.pushState({}, '', '/admin/translations-admin/' + cat);
      }
    },
    toggleSidebar () {
      document.body.classList.toggle('showing-sidebar');
    }
  }
};
</script>

<style scoped>
#sidebar {
    height: 100%;
}
</style>


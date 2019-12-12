<template>
    <div>
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
    EventBus.$emit('initial-category', this.category);
  },
  methods: {
    changeCategory (cat, pushState = true) {
      this.category = cat;
      EventBus.$emit('category-changed', cat);
      if (pushState) {
        window.history.pushState({}, '', '/admin/translations-admin/' + cat);
      }
    }
  }
};
</script>


<template>
    <div>
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
    categories: Array
  },
  data () {
    return {
      category: null
    };
  },
  mounted () {
    this.changeCategory(this.categories[0]);
  },
  methods: {
    changeCategory (cat) {
      this.category = cat;
      EventBus.$emit('category-changed', cat);
    },
    toggleSidebar() {
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


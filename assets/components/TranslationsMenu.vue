<template>
    <div id="sidebar" class="sidebar" v-if="categories.length > 1">
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
import { EventBus } from "./../EventBus.js";

export default {
  props: {
    categories: String
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
    }
  }
};
</script>


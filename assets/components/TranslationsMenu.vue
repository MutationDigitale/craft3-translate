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
import {mapMutations, mapState} from 'vuex';

export default {
  props: {
    categories: Array
  },
  computed: {
    ...mapState({
      category: state => state.category,
    })
  },
  created() {
    window.addEventListener('popstate', () => {
      const pathSplit = document.location.pathname.split('/');
      if (pathSplit.length >= 3 && pathSplit[2] === 'translations-admin') {
        this.changeCategory(pathSplit.length >= 4 ? pathSplit[3] : this.categories[0], false);
      }
    });
  },
  methods: {
    changeCategory(cat, pushState = true) {
      this.setCategory(cat);
      if (pushState) {
        window.history.pushState({}, '', `/${this.$craft.cpTrigger}/translations-admin/${cat}`);
      }
    },
    ...mapMutations({
      setCategory: 'setCategory',
    })
  }
};
</script>


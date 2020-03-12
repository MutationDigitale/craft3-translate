<template>
    <div id="count-container" class="light flex-grow">
        <div class="flex pagination">
            <div class="page-link page-first" :class="{'disabled': page === 1}"
                 @click="setPage(1)">«
            </div>
            <div class="page-link" data-icon="leftangle" :title="t('Previous Page')"
                 :class="{'disabled': page === 1}" @click="page > 1 ? setPage(page-1) : null"></div>

            <div class="page-link page-number"
                    :class="{'active': pageNumber === page}"
                    v-for="pageNumber in pages.slice(page < 3 ? 0 : page - 3, page + 3)"
                    v-bind:key="pageNumber"
                    @click="setPage(pageNumber)">
                {{pageNumber}}
            </div>

            <div class="page-link" data-icon="rightangle" :title="t('Next Page')"
                 :class="{'disabled': page === pages.length}"
                 @click="page < pages.length ? setPage(page+1) : null"></div>

            <div class="page-link page-last" :class="{'disabled': page === pages.length}"
                 @click="setPage(pages.length)">»
            </div>

            <div class="page-info">
                {{(page-1)*perPage}}-{{((page-1)*perPage)+displayedSourceMessages.length}} {{t('of')}}
                {{filteredSourceMessages.length}} {{t('translations')}}
            </div>
        </div>
    </div>
</template>

<script>
import { mapGetters, mapMutations, mapState } from 'vuex';

export default {
  data () {
    return {
      pages: [],
    };
  },
  computed: {
    ...mapState({
      page: state => state.page,
      perPage: state => state.perPage,
      filteredSourceMessages: state => state.filteredSourceMessages
    }),
    ...mapGetters({
      displayedSourceMessages: 'displayedSourceMessages'
    })
  },
  watch: {
    filteredSourceMessages () {
      this.setPages();
    }
  },
  methods: {
    setPages () {
      let numberOfPages = Math.ceil(this.filteredSourceMessages.length / this.perPage);
      this.pages = [];
      for (let index = 1; index <= numberOfPages; index++) {
        this.pages.push(index);
      }
      if (this.page > this.pages.length && this.pages.length > 0) {
        this.setPage(this.pages.length);
      }
    },
    setPage (nb) {
      this.setPage(nb);
    },
    t (str) {
      return this.$craft.t('translations-admin', str);
    },
    ...mapMutations({
      setPage: 'setPage',
    }),
  }
};
</script>

<style lang="scss" scoped>
@import "~craftcms-sass/mixins";

.page-first,
.page-last {
    font-size: 1.4em;
}

.page-number {
    padding-top: 6px;
    padding-bottom: 6px;
}

.page-number.active {
    pointer-events: none;
    background: $grey200;
}
</style>

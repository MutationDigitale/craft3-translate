<template>
    <div class="translate-pagination">
        <div class="light page-info">
            {{(page-1)*perPage}}-{{((page-1)*perPage)+displayedSourceMessages.length}} {{t('translations')}} /
            {{filteredSourceMessages.length}}
        </div>

        <div v-show="pages.length > 1">
            <button class="btn page-link" type="button"
                    :disabled="page === 1"
                    @click="setPage(1)">«
            </button>
            <button class="btn page-link" type="button" data-icon="leftangle"
                    :disabled="page === 1"
                    @click="setPage(page-1)"></button>

            <button class="btn page-link" type="button"
                    :class="{'active': pageNumber === page}"
                    v-for="pageNumber in pages.slice(page < 3 ? 0 : page - 3, page + 3)"
                    v-bind:key="pageNumber"
                    @click="setPage(pageNumber)">
                {{pageNumber}}
            </button>

            <button class="btn page-link" type="button" data-icon="rightangle"
                    :disabled="page === pages.length"
                    @click="setPage(page+1)"></button>
            <button class="btn page-link" type="button"
                    :disabled="page === pages.length"
                    @click="setPage(pages.length)">»
            </button>
        </div>
    </div>
</template>

<script>
import { EventBus } from '../EventBus';

export default {
  data () {
    return {
      filteredSourceMessages: [],
      page: 1,
      perPage: 40,
      pages: [],
    };
  },
  created () {
    EventBus.$on('translations-filtered', (translations) => {
      this.filteredSourceMessages = translations;
    });
  },
  computed: {
    displayedSourceMessages () {
      return this.paginate(this.filteredSourceMessages);
    }
  },
  watch: {
    filteredSourceMessages () {
      this.setPages();
    },
    displayedSourceMessages () {
      EventBus.$emit('translations-paginated', this.displayedSourceMessages);
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
        this.page = this.pages.length;
      }
    },
    paginate (sourceMessages) {
      let page = this.page;
      let perPage = this.perPage;
      let from = (page * perPage) - perPage;
      let to = (page * perPage);
      return sourceMessages.slice(from, to);
    },
    setPage (nb) {
      this.page = nb;
      EventBus.$emit('set-page', this.page);
    },
    t (str) {
      return this.$craft.t('translations-admin', str);
    },
  }
};
</script>

<style scoped>
.translate-pagination {
    width: 100%;
    display: flex;
    align-items: center;
}

.translate-pagination .page-info {
    margin-right: auto;
    padding: 6px 0;
}

.translate-pagination .page-link {
    margin-left: 12px;
}

.translate-pagination .page-link.active {
    pointer-events: none;
    background: rgba(0, 0, 20, 0.1);
}

.translate-pagination .page-link:disabled {
    pointer-events: none;
    opacity: 0.5;
}
</style>

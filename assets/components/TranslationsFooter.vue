<template>
  <div id="count-container" class="light">
    <div class="flex pagination">
      <div class="page-link page-first" :title="t('First Page')" :class="{'disabled': page === 1}"
           @click="setPage(1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M8.354 1.646a.5.5 0 0 1 0 .708L2.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
          <path fill-rule="evenodd" d="M12.354 1.646a.5.5 0 0 1 0 .708L6.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
        </svg>
      </div>

      <div class="page-link prev-page" :title="t('Previous Page')"
           :class="{'disabled': page === 1}" @click="page > 1 ? setPage(page-1) : null"></div>

      <div class="page-link page-number"
           :class="{'active': pageNumber === page}"
           v-for="pageNumber in pages.slice(page < 3 ? 0 : page - 3, page + 3)"
           v-bind:key="pageNumber"
           @click="setPage(pageNumber)">
        {{ pageNumber }}
      </div>

      <div class="page-link next-page" :title="t('Next Page')"
           :class="{'disabled': page === pages.length}"
           @click="page < pages.length ? setPage(page+1) : null"></div>

      <div class="page-link page-last" :title="t('Last Page')"
           :class="{'disabled': page === pages.length}"
           @click="setPage(pages.length)">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M3.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L9.293 8 3.646 2.354a.5.5 0 0 1 0-.708z"/>
          <path fill-rule="evenodd" d="M7.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L13.293 8 7.646 2.354a.5.5 0 0 1 0-.708z"/>
        </svg>
      </div>

      <div class="page-info">
        {{ (page - 1) * perPage }}-{{ ((page - 1) * perPage) + displayedSourceMessages.length }} {{ t('of') }}
        {{ filteredSourceMessages.length }} {{ t('translations') }}
      </div>
    </div>
  </div>
  <div v-show="checkedSourceMessages.length > 0">
    <button v-if="deletePermission" class="btn secondary" data-icon="trash"  @click="deleteMessages()">
      {{ t('Delete') }}
    </button>
  </div>
  <div>
    <button v-if="exportPermission" class="btn" type="button" @click="exportMessages()" :disabled="isExporting">
      {{ t('Export') }}
    </button>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex';
import axios from 'axios';

export default {
  props: {
    deletePermission: Boolean,
    exportPermission: Boolean,
  },
  data() {
    return {
      pages: [],
      isExporting: false,
    };
  },
  computed: {
    ...mapState({
      page: state => state.page,
      perPage: state => state.perPage,
      sourceMessages: state => state.sourceMessages,
      filteredSourceMessages: state => state.filteredSourceMessages,
      checkedSourceMessages: state => state.checkedSourceMessages,
      category: state => state.category
    }),
    ...mapGetters({
      displayedSourceMessages: 'displayedSourceMessages'
    })
  },
  watch: {
    filteredSourceMessages: {
      handler() {
        this.setPages();
      },
      deep: true
    }
  },
  methods: {
    setPages() {
      let numberOfPages = Math.ceil(this.filteredSourceMessages.length / this.perPage);
      this.pages = [];
      for (let index = 1; index <= numberOfPages; index++) {
        this.pages.push(index);
      }
      if (this.page > this.pages.length && this.pages.length > 0) {
        this.setPage(this.pages.length);
      }
    },
    setPage(nb) {
      this.setPage(nb);
    },
    t(str) {
      return this.$craft.t('translations-admin', str);
    },
    deleteMessages() {
      if (!confirm(this.t('Are you sure you want to delete the selected translations?'))) {
        return;
      }

      this.setIsDeleting(true);

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translations-admin/messages/delete');

      for (const sourceMessageId of this.checkedSourceMessages) {
        formData.append('sourceMessageId[]', sourceMessageId);
      }

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            this.emitter.emit('translation-deleted');
            const sourceMessages = this.sourceMessages.filter((sourceMessage) => {
              return this.checkedSourceMessages.indexOf(sourceMessage.id) === -1;
            });
            this.updateSourceMessages(sourceMessages);
            this.setCheckedSourceMessages([]);
          } else {
            this.emitter.emit('translation-deleted-error');
          }
        })
        .catch(() => {
          this.emitter.emit('translation-deleted-error');
        })
        .finally(() => {
          this.setIsDeleting(false);
        });
    },
    exportMessages() {
      this.isExporting = true;

      const formData = {};

      formData[this.$csrfTokenName] = this.$csrfTokenValue;
      formData['category'] = this.category;

      if (this.checkedSourceMessages && this.checkedSourceMessages.length > 0) {
        formData['sourceMessageId'] = [];
        for (const sourceMessageId of this.checkedSourceMessages) {
          formData['sourceMessageId'].push(sourceMessageId);
        }
      }

      this.$craft.downloadFromUrl(
        'POST',
        this.$craft.getActionUrl('translations-admin/export/export'),
        formData
      )
        .catch((e) => {
            if (!axios.isCancel(e)) {
              // Error
            }
        })
        .finally(() => {
          this.isExporting = false;
        });
    },
    ...mapMutations({
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setIsDeleting: 'setIsDeleting',
      setPage: 'setPage',
    }),
    ...mapActions({
      updateSourceMessages: 'updateSourceMessages'
    })
  }
};
</script>

<style lang="scss" scoped>
@import "~craftcms-sass/mixins";

.page-number.active {
  pointer-events: none;
  background: $grey200;
}
</style>

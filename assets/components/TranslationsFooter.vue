<template>
  <div id="count-container" class="light">
    <div class="flex pagination">
      <button type="button" class="page-link page-first" :title="t('First Page')" :aria-label="t('First Page')"
              :class="{'disabled': page === 1}" :disabled="page === 1" @click="setPage(1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
          <path fill-rule="evenodd" d="M8.354 1.646a.5.5 0 0 1 0 .708L2.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
          <path fill-rule="evenodd" d="M12.354 1.646a.5.5 0 0 1 0 .708L6.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
        </svg>
      </button>

      <button type="button" class="page-link prev-page" :title="t('Previous Page')" :aria-label="t('Previous Page')"
              :class="{'disabled': page === 1}" :disabled="page === 1" @click="setPage(page - 1)"></button>

      <button type="button" class="page-link page-number"
              :class="{'active': pageNumber === page}"
              :aria-current="pageNumber === page ? 'page' : null"
              v-for="pageNumber in visiblePages"
              v-bind:key="pageNumber"
              @click="setPage(pageNumber)">
        {{ pageNumber }}
      </button>

      <button type="button" class="page-link next-page" :title="t('Next Page')" :aria-label="t('Next Page')"
              :class="{'disabled': page >= lastPage}" :disabled="page >= lastPage" @click="setPage(page + 1)"></button>

      <button type="button" class="page-link page-last" :title="t('Last Page')" :aria-label="t('Last Page')"
              :class="{'disabled': page >= lastPage}" :disabled="page >= lastPage" @click="setPage(lastPage)">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
          <path fill-rule="evenodd" d="M3.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L9.293 8 3.646 2.354a.5.5 0 0 1 0-.708z"/>
          <path fill-rule="evenodd" d="M7.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L13.293 8 7.646 2.354a.5.5 0 0 1 0-.708z"/>
        </svg>
      </button>

      <div class="page-info">
        {{ displayedSourceMessages.length > 0 ? ((page - 1) * perPage) + 1 : 0 }}-{{ ((page - 1) * perPage) + displayedSourceMessages.length }} {{ t('of') }}
        {{ filteredSourceMessages.length }} {{ t('translations') }}
      </div>
    </div>
  </div>
  <div class="flex">
    <button v-show="checkedSourceMessages.length === 0 && copiedMessages.length > 0 && copiedMessagesCategory !== category"
            v-if="addPermission"
            type="button"
            class="btn paste-btn"
            @click="pasteMessages()">
      <span class="inline-flex gap-xs">
        <span class="cp-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M0 130h64v92H0v-92zM130 64V0h92v64h-92zM64 98V64h34V0H64C28.7 0 0 28.7 0 64v34M254 64h34v64h64V64c0-35.3-28.7-64-64-64h-34M128 288H64v-34H0v34c0 35.3 28.7 64 64 64h64M448 160H224c-35.3 0-64 28.7-64 64v224c0 35.3 28.7 64 64 64h224c35.3 0 64-28.7 64-64V224c0-35.3-28.7-64-64-64zm0 259v29H224V224h224v195z"></path></svg></span>
        <span class="label">{{ t('Paste translations') }}</span>
      </span>
    </button>
    <button v-show="checkedSourceMessages.length > 0"
            v-if="deletePermission"
            class="btn secondary"
            data-icon="trash"
            @click="deleteMessages()">
      {{ t('Delete') }}
    </button>
    <button v-show="checkedSourceMessages.length > 0"
            v-if="addPermission"
            type="button"
            class="btn secondary"
            @click="copyMessages()">
      <span class="inline-flex gap-xs">
        <span class="cp-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M0 130h64v92H0v-92zM130 64V0h92v64h-92zM64 98V64h34V0H64C28.7 0 0 28.7 0 64v34M254 64h34v64h64V64c0-35.3-28.7-64-64-64h-34M128 288H64v-34H0v34c0 35.3 28.7 64 64 64h64M448 160H224c-35.3 0-64 28.7-64 64v224c0 35.3 28.7 64 64 64h224c35.3 0 64-28.7 64-64V224c0-35.3-28.7-64-64-64zm0 259v29H224V224h224v195z"></path></svg></span>
        <span class="label">{{ t('Copy translations') }}</span>
      </span>
    </button>
  </div>
  <div>
    <!-- Nothing to export when no message matches the search and filters -->
    <button v-if="exportPermission" class="btn" type="button" @click="exportMessages()"
            :disabled="isExporting || (sourceMessages.length > 0 && filteredSourceMessages.length === 0)">
      {{ t('Export') }}
    </button>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex';
import axios from 'axios';
import { clone, postAction } from '../helpers';

export default {
  props: {
    addPermission: Boolean,
    deletePermission: Boolean,
    exportPermission: Boolean,
  },
  data() {
    return {
      copiedMessages: [],
      copiedMessagesCategory: '',
      isExporting: false,
    };
  },
  computed: {
    pageCount() {
      return Math.ceil(this.filteredSourceMessages.length / this.perPage);
    },
    // The first page is shown even when no message matches the filters
    lastPage() {
      return Math.max(this.pageCount, 1);
    },
    // The 2 pages before the current one and the 3 after it
    visiblePages() {
      const pages = [];
      for (let pageNumber = Math.max(this.page - 2, 1); pageNumber <= Math.min(this.page + 3, this.pageCount); pageNumber++) {
        pages.push(pageNumber);
      }
      return pages;
    },
    ...mapState({
      page: state => state.page,
      perPage: state => state.perPage,
      sourceMessages: state => state.sourceMessages,
      originalSourceMessages: state => state.originalSourceMessages,
      filteredSourceMessages: state => state.filteredSourceMessages,
      checkedSourceMessages: state => state.checkedSourceMessages,
      category: state => state.category
    }),
    ...mapGetters({
      sortedSourceMessages: 'sortedSourceMessages',
      displayedSourceMessages: 'displayedSourceMessages'
    })
  },
  watch: {
    lastPage() {
      if (this.page > this.lastPage) {
        this.setPage(this.lastPage);
      }
    }
  },
  methods: {
    t(str, params = {}) {
      return this.$craft.t('translations-admin', str, params);
    },
    deleteMessages() {
      const sourceMessageIds = [...this.checkedSourceMessages];
      if (!confirm(this.t('Are you sure you want to delete {count, plural, =1{the selected translation} other{the # selected translations}}?', { count: sourceMessageIds.length }))) {
        return;
      }

      this.setIsDeleting(true);

      postAction(this, 'translations-admin/messages/delete', { sourceMessageId: sourceMessageIds })
        .then((response) => {
          if (response.data.success) {
            this.emitter.emit('translation-deleted');
            this.removeSourceMessages(sourceMessageIds);
            // The changes of the deleted messages can't be saved anymore
            this.forgetModifiedMessages({ sourceMessageIds });
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
    copyMessages() {
      // The saved translations: the unsaved changes can still be discarded (e.g. by changing category)
      const checkedIds = new Set(this.checkedSourceMessages);
      this.copiedMessages = clone(this.originalSourceMessages.filter(s => checkedIds.has(s.id)));
      this.copiedMessagesCategory = this.category;
      this.emitter.emit('translations-copied');
    },
    pasteMessages() {
      this.setIsAdding(true);

      const category = this.category;
      const sourceMessages = this.copiedMessages.map(s => ({ message: s.message, languages: s.languages }));

      postAction(this, 'translations-admin/messages/add-multiple', { category, sourceMessages })
        .then((response) => {
          if (response.data.success) {
            // The messages already in the category are skipped
            this.emitter.emit('translations-pasted', sourceMessages.length - response.data.sourceMessages.length);
            // Not in the list of another category, if it was changed during the request
            if (category === this.category) {
              this.addSourceMessages(response.data.sourceMessages);
            }
          } else {
            this.emitter.emit('translations-pasted-error', this.t('Translations not pasted, the messages are already in the category.'));
          }
          // Pasting them again would give the same result (e.g. when they're all in the category already)
          this.copiedMessages = [];
          this.copiedMessagesCategory = '';
        })
        .catch(() => {
          // The copied messages are kept, to try again
          this.emitter.emit('translations-pasted-error');
        })
        .finally(() => {
          this.setIsAdding(false);
        });
    },
    exportMessages() {
      this.isExporting = true;

      const formData = {};

      formData[this.$csrfTokenName] = this.$csrfTokenValue;
      formData['category'] = this.category;

      // The selected messages, or the ones matching the search and filters, in the displayed order. Without IDs, the whole
      // category is exported.
      if (this.checkedSourceMessages.length > 0) {
        const checkedIds = new Set(this.checkedSourceMessages);
        formData['sourceMessageId'] = this.sortedSourceMessages.filter(s => checkedIds.has(s.id)).map(s => s.id);
      } else if (this.filteredSourceMessages.length < this.sourceMessages.length) {
        formData['sourceMessageId'] = this.sortedSourceMessages.map(s => s.id);
      }

      this.$craft.downloadFromUrl(
        'POST',
        this.$craft.getActionUrl('translations-admin/export/export'),
        formData
      )
        .catch((e) => {
          if (!axios.isCancel(e)) {
            this.emitter.emit('translations-exported-error');
          }
        })
        .finally(() => {
          this.isExporting = false;
        });
    },
    ...mapMutations({
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setIsAdding: 'setIsAdding',
      setIsDeleting: 'setIsDeleting',
      setPage: 'setPage',
    }),
    ...mapActions({
      addSourceMessages: 'addSourceMessages',
      removeSourceMessages: 'removeSourceMessages',
      forgetModifiedMessages: 'forgetModifiedMessages'
    })
  }
};
</script>

<style lang="scss" scoped>
@use "craftcms-sass/mixins" as *;

.page-number.active {
  pointer-events: none;
  background: $grey200;
}
</style>

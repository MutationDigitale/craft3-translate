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
    <button v-if="exportPermission" class="btn" type="button" @click="exportMessages()" :disabled="isExporting">
      {{ t('Export') }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useStore } from 'vuex';
import axios from 'axios';
import { clone, postAction, useCraft } from '../helpers';

defineProps({
  addPermission: Boolean,
  deletePermission: Boolean,
  exportPermission: Boolean,
});

const store = useStore();
const { globals, craft, t } = useCraft();

const copiedMessages = ref([]);
const copiedMessagesCategory = ref('');
const isExporting = ref(false);

const page = computed(() => store.state.page);
const perPage = computed(() => store.state.perPage);
const category = computed(() => store.state.category);
const filteredSourceMessages = computed(() => store.state.filteredSourceMessages);
const checkedSourceMessages = computed(() => store.state.checkedSourceMessages);
const displayedSourceMessages = computed(() => store.getters.displayedSourceMessages);

const pageCount = computed(() => Math.ceil(filteredSourceMessages.value.length / perPage.value));
// The first page is shown even when no message matches the filters
const lastPage = computed(() => Math.max(pageCount.value, 1));
// The 2 pages before the current one and the 3 after it
const visiblePages = computed(() => {
  const pages = [];
  for (let pageNumber = Math.max(page.value - 2, 1); pageNumber <= Math.min(page.value + 3, pageCount.value); pageNumber++) {
    pages.push(pageNumber);
  }
  return pages;
});

watch(lastPage, () => {
  if (page.value > lastPage.value) {
    setPage(lastPage.value);
  }
});

function setPage (value) {
  store.commit('setPage', value);
}

function deleteMessages () {
  const sourceMessageIds = [...checkedSourceMessages.value];
  if (!confirm(t('Are you sure you want to delete {count, plural, =1{the selected translation} other{the # selected translations}}?', { count: sourceMessageIds.length }))) {
    return;
  }

  store.commit('setIsDeleting', true);

  postAction(globals, 'translations-admin/messages/delete', { sourceMessageId: sourceMessageIds })
    .then((response) => {
      if (response.data.success) {
        craft.cp.displayNotice(t('{count, plural, =1{Translation deleted} other{# translations deleted}}', { count: sourceMessageIds.length }));
        store.dispatch('removeSourceMessages', sourceMessageIds);
        // The changes of the deleted messages can't be saved anymore
        store.dispatch('forgetModifiedMessages', { sourceMessageIds });
        store.commit('setCheckedSourceMessages', []);
      } else {
        craft.cp.displayError(t('{count, plural, =1{Translation not deleted} other{Translations not deleted}}', { count: sourceMessageIds.length }));
      }
    })
    .catch(() => {
      craft.cp.displayError(t('{count, plural, =1{Translation not deleted} other{Translations not deleted}}', { count: sourceMessageIds.length }));
    })
    .finally(() => {
      store.commit('setIsDeleting', false);
    });
}

function copyMessages () {
  // The saved translations: the unsaved changes can still be discarded (e.g. by changing category)
  const checkedIds = new Set(checkedSourceMessages.value);
  copiedMessages.value = clone(store.state.originalSourceMessages.filter(s => checkedIds.has(s.id)));
  copiedMessagesCategory.value = category.value;
  craft.cp.displayNotice(t('Translations copied'));
}

function pasteMessages () {
  store.commit('setIsAdding', true);

  const pasteCategory = category.value;
  const sourceMessages = copiedMessages.value.map(s => ({ message: s.message, languages: s.languages }));

  postAction(globals, 'translations-admin/messages/add-multiple', { category: pasteCategory, sourceMessages })
    .then((response) => {
      if (response.data.success) {
        // The messages already in the category are skipped
        const skipped = sourceMessages.length - response.data.sourceMessages.length;
        craft.cp.displayNotice(skipped > 0
          ? t('Translations pasted, except {count, plural, =1{one message} other{# messages}} already in the category.', { count: skipped })
          : t('Translations pasted'));
        // Not in the list of another category, if it was changed during the request
        if (pasteCategory === category.value) {
          store.dispatch('addSourceMessages', response.data.sourceMessages);
        }
      } else {
        craft.cp.displayError(t('Translations not pasted, the messages are already in the category.'));
      }
      // Pasting them again would give the same result (e.g. when they're all in the category already)
      copiedMessages.value = [];
      copiedMessagesCategory.value = '';
    })
    .catch(() => {
      // The copied messages are kept, to try again
      craft.cp.displayError(t('Translations not pasted'));
    })
    .finally(() => {
      store.commit('setIsAdding', false);
    });
}

function exportMessages () {
  isExporting.value = true;

  const formData = {};

  formData[globals.$csrfTokenName] = globals.$csrfTokenValue;
  formData['category'] = category.value;

  // The selected messages, or the whole category
  if (checkedSourceMessages.value.length > 0) {
    formData['sourceMessageId'] = [...checkedSourceMessages.value];
  }

  craft.downloadFromUrl(
    'POST',
    craft.getActionUrl('translations-admin/export/export'),
    formData
  )
    .catch((e) => {
      if (!axios.isCancel(e)) {
        craft.cp.displayError(t('Translations could not be exported'));
      }
    })
    .finally(() => {
      isExporting.value = false;
    });
}
</script>

<style lang="scss" scoped>
@use "craftcms-sass/mixins" as *;

.page-number.active {
  pointer-events: none;
  background: $grey200;
}
</style>

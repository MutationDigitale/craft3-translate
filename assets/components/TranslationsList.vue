<template>
  <div class="elements" :class="{'busy': isLoading || isAdding || isDeleting}">
    <div class="tableview tablepane">
      <table class="data fullwidth translate-table"
             ref="translateTable">
        <thead>
        <tr>
          <th class="checkbox-cell selectallcontainer" role="checkbox" tabindex="0"
              :aria-checked="ariaChecked" :aria-label="t('Select all')" style="width: 4%" @click="toggleCheckedSourceMessages()"
              @keydown.space.prevent="toggleCheckedSourceMessages()"
              @keydown.enter.prevent="toggleCheckedSourceMessages()">
            <div class="checkbox"
                 :class="{'checked': ariaChecked === 'true', 'indeterminate': ariaChecked === 'mixed'}"></div>
          </th>
          <th class="orderable" :class="[{'ordered': sortProperty === 'message'}, sortDirection]"
              :aria-sort="ariaSort('message')">
            <button type="button" @click="changeSort('message')">{{ t('Key') }}</button>
          </th>
          <th v-for="language in checkedLanguages" v-bind:key="language.id" :id="'language-header-' + language.id">
            <span>{{ language.displayName }}</span>
            <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
          </th>
          <th v-if="columns['dateCreated'].checked"
              class="orderable date-column" :class="[{'ordered': sortProperty === 'dateCreated'}, sortDirection]"
              :aria-sort="ariaSort('dateCreated')">
            <button type="button" @click="changeSort('dateCreated')">
              {{ t('Date Created') }}
            </button>
          </th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="sourceMessage in displayedSourceMessages" v-bind:key="sourceMessage.id"
            :class="{'sel': checkedSourceMessages.indexOf(sourceMessage.id) > -1}">
          <td class="checkbox-cell">
            <input :id="'source-message-' + sourceMessage.id"
                   type="checkbox"
                   class="checkbox source-message-checkbox"
                   :title="t('Select')"
                   :value="sourceMessage.id"
                   :aria-labelledby="'source-message-label-' + sourceMessage.id + ' source-message-key-' + sourceMessage.id"
                   v-model="checkedSourceMessages">
            <label :id="'source-message-label-' + sourceMessage.id"
                   :for="'source-message-' + sourceMessage.id"
                   @click="onSourceMessageCheckboxClick"><span class="visually-hidden">{{ t('Select') }}</span></label>
          </td>

          <td>
            <div class="mobile-only cell-label">{{ t('Key') }}</div>
            <pre :id="'source-message-key-' + sourceMessage.id">{{ sourceMessage.message }}</pre>
          </td>

          <td v-for="language in checkedLanguages" v-bind:key="language.id"
              :class="{'modified': sourceMessage.id in (modifiedMessages[language.id] ?? {})}">
            <div class="mobile-only cell-label">
              <span>{{ language.displayName }}</span>
              <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
            </div>
            <div class="message-text">
              <!-- Not keyup: the translations can be changed without the keyboard (e.g. pasted with the mouse or corrected by the spell checker) -->
              <!-- Named by the language and the key: the language badge is only visual -->
              <textarea class="text nicetext fullwidth"
                        :aria-labelledby="'language-header-' + language.id + ' source-message-key-' + sourceMessage.id"
                        v-model="sourceMessage.languages[language.id]"
                        @input="change(sourceMessage, language)"
                        :readonly="!savePermission"
                        :rows="getNumberOfLines(sourceMessage.languages[language.id])"
                        autocomplete="off"></textarea>
              <div class="language-label">{{ language.id }}</div>
            </div>
          </td>

          <td v-if="columns['dateCreated'].checked">
            <span class="mobile-only-inline">{{ t('Date Created') }}: </span>{{ sourceMessage.dateCreated }}
          </td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useStore } from 'vuex';
import axios from 'axios';
import { readLocalStorage, searchable, useCraft } from '../helpers';

const props = defineProps({
  currentCategory: String,
  savePermission: Boolean,
});

const store = useStore();
const { craft, emitter, t } = useCraft();

const translateTable = ref(null);
let lastCheckbox = null;
// Identifies the last load, the responses of the previous ones are ignored (e.g. after changing category)
let loadRequest = 0;

const isLoading = computed(() => store.state.isLoading);
const isAdding = computed(() => store.state.isAdding);
const isDeleting = computed(() => store.state.isDeleting);
const category = computed(() => store.state.category);
const languages = computed(() => store.state.languages);
const sourceMessages = computed(() => store.state.sourceMessages);
const page = computed(() => store.state.page);
const modifiedMessages = computed(() => store.state.modifiedMessages);
const emptyMessages = computed(() => store.state.emptyMessages);
const search = computed(() => store.state.search);
const sortProperty = computed(() => store.state.sortProperty);
const sortDirection = computed(() => store.state.sortDirection);
const columns = computed(() => store.state.columns);
const displayedSourceMessages = computed(() => store.getters.displayedSourceMessages);
const checkedSourceMessages = computed({
  get: () => store.state.checkedSourceMessages,
  set: value => store.commit('setCheckedSourceMessages', value),
});
const checkedLanguages = computed(() => languages.value.filter(lang => lang.checked === true));
// The saved translations, indexed by source message ID
const originalSourceMessagesById = computed(() => new Map(store.state.originalSourceMessages.map(sourceMessage => [sourceMessage.id, sourceMessage])));

const ariaChecked = computed(() => {
  if (checkedSourceMessages.value.length === 0) {
    return 'false';
  }
  return displayedSourceMessages.value.every(sourceMessage => checkedSourceMessages.value.includes(sourceMessage.id))
    ? 'true'
    : 'mixed';
});

function changeCategory () {
  store.commit('setLanguages', []);
  store.commit('setSourceMessages', []);
  store.commit('setFilteredSourceMessages', []);
  store.commit('setEmptyMessages', false);
  store.commit('setCheckedSourceMessages', []);
  store.commit('setPage', 1);
  store.dispatch('updateModifiedMessages', {});
  loadSourceMessages();
  loadColumns();
}

function loadSourceMessages () {
  const request = ++loadRequest;
  store.commit('setIsLoading', true);

  axios
    .get(craft.getActionUrl('translations-admin/messages/get-translations', { category: category.value }))
    .then((response) => {
      if (request !== loadRequest) {
        return;
      }
      const localStorageLanguages = readLocalStorage('admin-translations-languages');
      const loadedLanguages = response.data.languages;
      let i = 0;
      response.data.languages.forEach(lang => {
        const localStorageLanguage = localStorageLanguages?.find?.(o => o.id === lang.id);
        loadedLanguages[lang.id] = lang;
        loadedLanguages[lang.id].checked = localStorageLanguage ? localStorageLanguage.checked : i < 5;
        i++;
      });
      store.dispatch('updateLanguages', loadedLanguages);
      store.dispatch('updateSourceMessages', response.data.sourceMessages);
    })
    .catch(() => {
      if (request === loadRequest) {
        craft.cp.displayError(t('Translations could not be loaded'));
      }
    })
    .finally(() => {
      if (request === loadRequest) {
        store.commit('setIsLoading', false);
      }
    });
}

function loadColumns () {
  const localStorageColumns = readLocalStorage('admin-translations-columns');
  if (localStorageColumns) {
    store.commit('setColumns', localStorageColumns);
  }
}

function filterSourceMessages () {
  const searched = searchable(search.value).trim();
  let filtered = sourceMessages.value.filter((sourceMessage) => {
    if (searched === '' || sourceMessage.message == null) {
      return true;
    }
    for (const language of checkedLanguages.value) {
      const translation = sourceMessage.languages[language.id];
      if (translation && searchable(translation).includes(searched)) {
        return true;
      }
    }
    return searchable(sourceMessage.message).includes(searched);
  });
  if (emptyMessages.value) {
    // Empty in one of the displayed languages, or in one language (even when its column is hidden)
    const languageIds = emptyMessages.value === true ? checkedLanguages.value.map(language => language.id) : [emptyMessages.value];
    filtered = filtered.filter(sourceMessage => languageIds.some(id => (sourceMessage.languages[id] ?? '').trim() === ''));
  }
  store.commit('setFilteredSourceMessages', filtered);
}

// Like the server, a blank translation is empty, but the other ones are saved as is (e.g. with a trailing space). The line
// breaks are compared as \n: the textareas always give them as \n, even when they were saved as \r\n.
function normalizeStringValue (value) {
  return (value ?? '').trim() === '' ? '' : value.replace(/\r\n?/g, '\n');
}

// Whether a translation is different from the saved one
function isChanged (sourceMessage, language) {
  const originalSourceMessage = originalSourceMessagesById.value.get(sourceMessage.id);
  const originalValue = normalizeStringValue(originalSourceMessage.languages[language.id]);
  const newValue = normalizeStringValue(sourceMessage.languages[language.id]);
  return originalValue !== newValue;
}

function change (sourceMessage, language) {
  if (isChanged(sourceMessage, language)) {
    store.dispatch('updateModifiedMessages', {
      ...modifiedMessages.value,
      [language.id]: {
        ...modifiedMessages.value[language.id],
        [sourceMessage.id]: sourceMessage.languages[language.id] || ''
      }
    });
  } else {
    store.dispatch('forgetModifiedMessages', { sourceMessageIds: [sourceMessage.id], languageId: language.id });
  }
}

// The sort of a column, announced by screen readers
function ariaSort (property) {
  if (sortProperty.value !== property) {
    return null;
  }
  return sortDirection.value === 'asc' ? 'ascending' : 'descending';
}

function changeSort (property) {
  if (sortProperty.value !== property) {
    store.commit('setSortProperty', property);
    store.commit('setSortDirection', 'asc');
  } else {
    store.commit('setSortDirection', sortDirection.value === 'asc' ? 'desc' : 'asc');
  }
  store.commit('setPage', 1);
}

function toggleCheckedSourceMessages () {
  const checked = [];
  if (checkedSourceMessages.value.length === 0) {
    for (const sourceMessage of displayedSourceMessages.value) {
      checked.push(sourceMessage.id);
    }
  }
  store.commit('setCheckedSourceMessages', checked);
}

function getNumberOfLines (str) {
  return (str ?? '').split(/\r\n|\r|\n/).length;
}

// Selects the messages between the last checked one and this one with Shift
function onSourceMessageCheckboxClick (event) {
  const checkboxes = Array.from(translateTable.value.querySelectorAll('.source-message-checkbox'));
  const checkedCheckboxes = checkboxes.filter(c => c.checked);
  const checkbox = event.target.closest('label').previousElementSibling;
  const checkboxIndex = checkboxes.indexOf(checkbox);
  const firstCheckbox = checkboxes.find(el => el.checked);
  let rangeStart = null;
  if (lastCheckbox !== null && lastCheckbox !== checkbox && checkedCheckboxes.includes(lastCheckbox)) {
    rangeStart = lastCheckbox;
  } else if (firstCheckbox !== undefined && firstCheckbox !== checkbox) {
    rangeStart = firstCheckbox;
  }
  const rangeStartIndex = checkboxes.indexOf(rangeStart);
  const min = Math.min(checkboxIndex, rangeStartIndex);
  const max = Math.max(checkboxIndex, rangeStartIndex);

  if (event.shiftKey && rangeStart !== null) {
    const checked = [];
    for (let i = min; i <= max; i++) {
      checked.push(displayedSourceMessages.value[i].id);
    }
    checkedSourceMessages.value = checked;
  }

  if (!event.shiftKey) {
    lastCheckbox = !checkbox.checked ? checkbox : null;
  }
}

// Keeps the search and filters when messages are loaded, added or deleted
watch(sourceMessages, filterSourceMessages);
watch([search, emptyMessages], () => {
  filterSourceMessages();
  store.commit('setPage', 1);
});
watch(languages, filterSourceMessages, { deep: true });
// The actions only apply to the selected messages that are displayed, not to the ones of other pages or hidden by the search
// and filters, which the user can't see
watch(displayedSourceMessages, (displayed) => {
  const displayedIds = new Set(displayed.map(sourceMessage => sourceMessage.id));
  const checked = checkedSourceMessages.value.filter(id => displayedIds.has(id));
  if (checked.length < checkedSourceMessages.value.length) {
    store.commit('setCheckedSourceMessages', checked);
  }
});
watch(category, changeCategory);
watch(page, () => {
  document.documentElement.scrollTop = document.querySelector('#main').getBoundingClientRect().top +
    document.documentElement.scrollTop;
});

function onTranslationsSaved (savedMessages) {
  // Only the sent translations are saved, the ones changed during the request stay modified
  for (const sourceMessage of sourceMessages.value) {
    for (const languageId in savedMessages) {
      if (sourceMessage.id in savedMessages[languageId]) {
        originalSourceMessagesById.value.get(sourceMessage.id).languages[languageId] = savedMessages[languageId][sourceMessage.id];
        change(sourceMessage, { id: languageId });
      }
    }
  }
}

emitter.on('translations-saved', onTranslationsSaved);
onBeforeUnmount(() => emitter.off('translations-saved', onTranslationsSaved));

// After the watchers: the change of category loads the messages
store.commit('setCategory', props.currentCategory);
</script>

<style lang="scss" scoped>
@use "craftcms-sass/mixins" as *;

.translate-table {
  table-layout: fixed;
}

.translate-table th button {
  overflow: hidden;
  text-overflow: ellipsis;
}

.translate-table tr:nth-child(2n) td {
  background-color: #f8f9fa;
}

table.data.translate-table tr td.modified {
  background-color: $yellow050;
}

.translate-table pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  @include sans-serif-font();
}

.translate-table .date-column {
  width: 190px;
}

table.data.translate-table td.checkbox-cell input.checkbox,
table.data.translate-table td.checkbox-cell input.checkbox + label,
table.data.translate-table th.checkbox-cell input.checkbox,
table.data.translate-table th.checkbox-cell input.checkbox + label {
  position: absolute;
  top: calc(50% - 8px);
}

.message-text {
  position: relative;
}

.message-text textarea {
  overflow-x: hidden;
  min-height: 34px;
  padding-right: 40px;
  color: var(--gray-700);
}

.language-label {
  text-transform: lowercase;
  font-size: 11px;
  line-height: 11px;
  display: inline-block;
  position: absolute;
  top: 1px;
  right: 1px;
  background: $grey050;
  color: $grey800;
  border-radius: 2px;
  padding: 2px 5px 3px;
  pointer-events: none;
}

.cell-label {
  margin-bottom: 4px;
  font-weight: bold;
  color: rgba(0, 0, 0, 0.5);
}

.mobile-only {
  display: none;
}

.mobile-only-inline {
  display: none;
}

@media (max-width: 767px) {
  .mobile-only {
    display: block;
  }

  .mobile-only-inline {
    display: inline;
  }

  .language-label {
    display: none;
  }

  .message-text textarea {
    padding-right: 7px;
  }

  .translate-table thead {
    display: none;
  }

  .translate-table,
  .translate-table tbody,
  .translate-table tr,
  .translate-table td {
    display: block;
  }

  .translate-table tbody,
  .translate-table tr,
  .translate-table td {
    width: 100%;
    border: 0;
  }

  // Craft limits the width and height of the table cells, the content of a row would overlap the next one
  .tableview table.data.translate-table td {
    max-width: none;
    height: auto;
  }

  table.data.translate-table td.checkbox-cell {
    width: 100% !important;
    box-sizing: border-box;
    padding: 18px 12px;
  }

  body.ltr .tablepane table.data.translate-table td:first-child,
  body.ltr .tablepane table.data.translate-table th:first-child {
    padding-left: 12px;
  }

  body.ltr .tablepane table.data.translate-table td:last-child,
  body.ltr .tablepane table.data.translate-table th:last-child {
    padding-right: 12px;
  }

  .translate-table .date-column {
    width: auto;
  }
}
</style>

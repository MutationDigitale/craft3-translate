<template>
  <div id="translations-toolbar" class="flex flex-grow">
    <div>
      <button ref="filterButton" type="button" class="btn menubtn statusmenubtn" :aria-label="t('Filter')"><span class="status" :class="{'disabled': emptyMessages, 'all': !emptyMessages}"></span>{{
          filterLabel
        }}</button>
      <div class="menu">
        <ul class="padded">
          <li>
            <a :class="{'sel': !emptyMessages}"
               @click="setEmptyMessages(false)">
              <span class="status all"></span>{{ t('All') }}
            </a>
          </li>
          <!-- Like the previous versions, the languages of the displayed columns -->
          <li>
            <a :class="{'sel': emptyMessages === true}"
               @click="setEmptyMessages(true)">
              <span class="status disabled"></span>{{ t('Empty in any language') }}
            </a>
          </li>
          <!-- The messages without a translation in a language, even when its column is hidden -->
          <li v-for="language in languages" :key="language.id" :id="'filter-aria-option-' + language.id">
            <a :id="'filter-option-' + language.id"
               :class="{'sel': emptyMessages === language.id}"
               @click="setEmptyMessages(language.id)">
              <span class="status disabled"></span>{{ t('Empty in {language}', { language: language.displayName }) }}
            </a>
          </li>
        </ul>
      </div>
    </div>
    <div class="flex-grow texticon search icon search-container">
      <input class="text fullwidth" type="text" autocomplete="off" :placeholder="t('Search')" :aria-label="t('Search')"
             v-model="search">
    </div>
    <div>
      <button type="button" class="btn menubtn" data-icon="sliders" :aria-label="t('Columns')">{{ t('Columns') }}</button>
      <div class="menu">
        <ul class="padded checkbox-menu">
          <li v-for="language in languages" :key="language.id" class="checkbox-menu-item">
            <input :id="`language-checkbox-${language.id}`"
                   class="checkbox"
                   type="checkbox"
                   :checked="languages[language.id].checked"
                   @input="setLanguages(language.id, $event.target.checked)">
            <label :for="`language-checkbox-${language.id}`">
              <span>{{ language.displayName }}</span>
              <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
            </label>
          </li>
          <li v-for="column in columns" :key="column.key" class="checkbox-menu-item">
            <input :id="`column-checkbox-${column.key}`"
                   class="checkbox"
                   type="checkbox"
                   :checked="columns[column.key].checked"
                   @input="setColumns(column.key, $event.target.checked)">
            <label :for="`column-checkbox-${column.key}`">
              {{ t(column.name) }}
            </label>
          </li>
        </ul>
      </div>
    </div>
    <div v-if="addPermission" class="textarea-container">
      <textarea class="text" rows="1" v-model="messageToAdd" :placeholder="t('Message')" :aria-label="t('Message')"></textarea>
    </div>
    <div v-if="addPermission">
      <button class="btn" type="button" @click="addMessage()"
              :disabled="isAdding || messageToAdd === null || messageToAdd.trim() === ''">
        {{ t('Add') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useStore } from 'vuex';
import { postAction, useCraft } from '../helpers';

defineProps({
  addPermission: Boolean,
});

const store = useStore();
const { globals, craft, jquery, t } = useCraft();

const messageToAdd = ref('');
const filterButton = ref(null);

const isAdding = computed(() => store.state.isAdding);
const languages = computed(() => store.state.languages);
const columns = computed(() => store.state.columns);
const category = computed(() => store.state.category);
const emptyMessages = computed(() => store.state.emptyMessages);
const search = computed({
  get: () => store.state.search,
  set: value => store.commit('setSearch', value),
});

const filterLabel = computed(() => {
  if (!emptyMessages.value) {
    return t('All');
  }
  if (emptyMessages.value === true) {
    return t('Empty in any language');
  }
  const language = languages.value[emptyMessages.value];
  return t('Empty in {language}', { language: language?.displayName ?? emptyMessages.value });
});

// The menu of Craft only knows the options rendered when the page loaded: the options of the languages are added once
// they're loaded, so the menu closes when they're chosen, and they can be reached with the keyboard
function registerFilterOptions () {
  const menu = jquery?.(filterButton.value).data('menubtn')?.menu;
  if (!menu) {
    return;
  }
  // The options of the previous category's languages aren't in the menu anymore (it's detached from the page when closed)
  const inMenu = (index, option) => menu.$container[0].contains(option);
  menu.$options = menu.$options.filter(inMenu);
  if (menu.$ariaOptions) {
    menu.$ariaOptions = menu.$ariaOptions.filter(inMenu);
  }
  const $options = menu.$container.find('a').not(menu.$options);
  if ($options.length > 0) {
    menu.addOptions($options);
  }
}

// Once the options of the languages are rendered
watch(languages, registerFilterOptions, { flush: 'post' });

function setEmptyMessages (value) {
  store.commit('setEmptyMessages', value);
}

function setLanguages (languageId, value) {
  const updatedLanguages = languages.value;
  updatedLanguages[languageId].checked = value;
  store.dispatch('updateLanguages', updatedLanguages);
}

function setColumns (columnKey, value) {
  const updatedColumns = columns.value;
  updatedColumns[columnKey].checked = value;
  store.dispatch('updateColumns', updatedColumns);
}

function addMessage () {
  store.commit('setIsAdding', true);

  const message = messageToAdd.value;
  const addCategory = category.value;

  postAction(globals, 'translations-admin/messages/add', { message, category: addCategory })
    .then((response) => {
      if (response.data.success) {
        craft.cp.displayNotice(t('Translation added'));
        // Kept when it was changed during the request
        if (messageToAdd.value === message) {
          messageToAdd.value = '';
        }
        // Not in the list of another category, if it was changed during the request
        if (addCategory === category.value) {
          store.dispatch('addSourceMessages', [response.data.sourceMessage]);
        }
      } else {
        // The message is kept, to change it or try again
        craft.cp.displayError(response.data.error || t('Translation not added'));
      }
    })
    .catch(() => {
      craft.cp.displayError(t('Translation not added'));
    })
    .finally(() => {
      store.commit('setIsAdding', false);
    });
}
</script>

<style lang="scss" scoped>
@use "craftcms-sass/mixins" as *;

#translations-toolbar {
  flex-wrap: wrap;
}

.search-container {
  flex: 2;
}

.textarea-container {
  height: 34px;
  flex: 1;
}

textarea {
  min-width: 160px;
  overflow-x: hidden;
  min-height: 34px;
  height: 34px;
  width: 100%;
}

.checkbox-menu {
  padding: 6px 0;
}

.checkbox-menu-item {
  padding: 7px 0;
}
</style>

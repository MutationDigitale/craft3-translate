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

<script>
import {mapActions, mapMutations, mapState} from 'vuex';
import { postAction } from '../helpers';

export default {
  props: {
    addPermission: Boolean,
  },
  data() {
    return {
      messageToAdd: ''
    };
  },
  watch: {
    languages: {
      handler() {
        this.registerFilterOptions();
      },
      // Once the options of the languages are rendered
      flush: 'post'
    }
  },
  computed: {
    search: {
      get() {
        return this.$store.state.search;
      },
      set(value) {
        this.setSearch(value);
      }
    },
    filterLabel() {
      if (!this.emptyMessages) {
        return this.t('All');
      }
      if (this.emptyMessages === true) {
        return this.t('Empty in any language');
      }
      const language = this.languages[this.emptyMessages];
      return this.t('Empty in {language}', { language: language?.displayName ?? this.emptyMessages });
    },
    ...mapState({
      isAdding: state => state.isAdding,
      isDeleting: state => state.isDeleting,
      languages: state => state.languages,
      columns: state => state.columns,
      category: state => state.category,
      emptyMessages: state => state.emptyMessages,
      checkedSourceMessages: state => state.checkedSourceMessages
    })
  },
  methods: {
    // The menu of Craft only knows the options rendered when the page loaded: the options of the languages are added
    // once they're loaded, so the menu closes when they're chosen, and they can be reached with the keyboard
    registerFilterOptions() {
      const menu = this.$jquery?.(this.$refs.filterButton).data('menubtn')?.menu;
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
    },
    t(str, params = {}) {
      return this.$craft.t('translations-admin', str, params);
    },
    setLanguages(languageId, value) {
      const languages = this.languages;
      languages[languageId].checked = value;
      this.updateLanguages(languages);
    },
    setColumns(columnKey, value) {
      const columns = this.columns;
      columns[columnKey].checked = value;
      this.updateColumns(columns);
    },
    addMessage() {
      this.setIsAdding(true);

      const message = this.messageToAdd;
      const category = this.category;

      postAction(this, 'translations-admin/messages/add', { message, category })
        .then((response) => {
          if (response.data.success) {
            this.$craft.cp.displayNotice(this.t('Translation added'));
            // Kept when it was changed during the request
            if (this.messageToAdd === message) {
              this.messageToAdd = '';
            }
            // Not in the list of another category, if it was changed during the request
            if (category === this.category) {
              this.addSourceMessages([response.data.sourceMessage]);
            }
          } else {
            // The message is kept, to change it or try again
            this.$craft.cp.displayError(response.data.error || this.t('Translation not added'));
          }
        })
        .catch(() => {
          this.$craft.cp.displayError(this.t('Translation not added'));
        })
        .finally(() => {
          this.setIsAdding(false);
        });
    },
    ...mapMutations({
      setIsAdding: 'setIsAdding',
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setSearch: 'setSearch',
      setEmptyMessages: 'setEmptyMessages'
    }),
    ...mapActions({
      updateLanguages: 'updateLanguages',
      updateColumns: 'updateColumns',
      addSourceMessages: 'addSourceMessages'
    })
  }
};
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

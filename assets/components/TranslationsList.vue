<template>
  <div class="elements" :class="{'busy': isLoading || isAdding || isDeleting}">
    <div class="tableview tablepane">
      <table class="data fullwidth translate-table"
             ref="translateTable">
        <thead>
        <tr>
          <th class="checkbox-cell selectallcontainer" role="checkbox" tabindex="0"
              :aria-checked="ariaChecked" style="width: 4%" @click="toggleCheckedSourceMessages()">
            <div class="checkbox"
                 :class="{
                    'checked': checkedSourceMessages.length > 0 &&
                        checkedSourceMessages.length === displayedSourceMessages.length,
                    'indeterminate': checkedSourceMessages.length > 0 &&
                        checkedSourceMessages.length !== displayedSourceMessages.length
                 }"></div>
          </th>
          <th class="orderable" :class="[{'ordered': sortProperty === 'message'}, sortDirection]">
            <button type="button" @click="changeSort('message')">{{ t('Key') }}</button>
          </th>
          <th v-for="language in checkedLanguages" v-bind:key="language.id">
            <span>{{ language.displayName }}</span>
            <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
          </th>
          <th v-if="columns['dateCreated'].checked"
              class="orderable date-column" :class="[{'ordered': sortProperty === 'dateCreated'}, sortDirection]">
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
                   v-model="checkedSourceMessages">
            <label :for="'source-message-' + sourceMessage.id"
                   @click="onSourceMessageCheckboxClick"></label>
          </td>

          <td>
            <div class="mobile-only cell-label">{{ t('Key') }}</div>
            <pre>{{ sourceMessage.message }}</pre>
          </td>

          <td v-for="language in checkedLanguages" v-bind:key="language.id"
              :class="{'modified': !isNullOrUndefined(sourceMessage.isModified) &&
                                   sourceMessage.isModified[language.id] === true}">
            <div class="mobile-only cell-label">
              <span>{{ language.displayName }}</span>
              <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
            </div>
            <div class="message-text">
              <textarea class="text nicetext fullwidth"
                        v-model="sourceMessage.languages[language.id]"
                        @change="change(sourceMessage, language)"
                        @keyup="change(sourceMessage, language)"
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

<script>
import axios from 'axios';
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex';

export default {
  props: {
    currentCategory: String
  },
  data () {
    return {
      lastCheckbox: null
    };
  },
  computed: {
    ariaChecked: function () {
      if (this.checkedSourceMessages.length === 0) {
        return 'false';
      } else {
        if (this.checkedSourceMessages.length === this.displayedSourceMessages.length) {
          return 'true';
        } else {
          return 'mixed';
        }
      }
    },
    checkedSourceMessages: {
      get () {
        return this.$store.state.checkedSourceMessages;
      },
      set (value) {
        this.setCheckedSourceMessages(value);
      }
    },
    checkedLanguages: function () {
      return this.languages.filter(lang => lang.checked === true);
    },
    ...mapState({
      isLoading: state => state.isLoading,
      isAdding: state => state.isAdding,
      isDeleting: state => state.isDeleting,
      category: state => state.category,
      languages: state => state.languages,
      originalSourceMessages: state => state.originalSourceMessages,
      sourceMessages: state => state.sourceMessages,
      filteredSourceMessages: state => state.filteredSourceMessages,
      page: state => state.page,
      displayedSourceMessages: state => state.displayedSourceMessages,
      modifiedMessages: state => state.modifiedMessages,
      emptyMessages: state => state.emptyMessages,
      search: state => state.search,
      sortProperty: state => state.sortProperty,
      sortDirection: state => state.sortDirection,
      columns: state => state.columns,
    }),
    ...mapGetters({
      displayedSourceMessages: 'displayedSourceMessages'
    })
  },
  created () {
    this.setCategory(this.currentCategory);

    this.emitter.on('translations-saved', () => {
      this.setOriginalSourceMessages(this.copyObj(this.sourceMessages));
      this.updateModifiedMessages({});
      const sourceMessagesLength = this.sourceMessages.length;
      for (let i = 0; i < sourceMessagesLength; i++) {
        this.sourceMessages[i].isModified = null;
      }
    });
  },
  watch: {
    search () {
      this.filterSourceMessages();
    },
    emptyMessages () {
      this.filterSourceMessages();
    },
    languages: {
      handler () {
        this.filterSourceMessages();
      },
      deep: true
    },
    category () {
      this.changeCategory();
    },
    page () {
      document.documentElement.scrollTop = document.querySelector('#main').getBoundingClientRect().top +
        document.documentElement.scrollTop;
    }
  },
  methods: {
    changeCategory () {
      this.setLanguages([]);
      this.setSourceMessages([]);
      this.setFilteredSourceMessages([]);
      this.setEmptyMessages(false);
      this.setCheckedSourceMessages([]);
      this.updateModifiedMessages({});
      this.loadSourceMessages();
      this.loadColumns();
    },
    loadSourceMessages () {
      this.setIsLoading(true);

      axios
        .get(this.$craft.getActionUrl('translations-admin/messages/get-translations', { category: this.category }))
        .then((response) => {
          const localStorageLanguages = localStorage.getItem('admin-translations-languages');
          let localStorageLanguagesObjects = null;
          try {
            localStorageLanguagesObjects = localStorageLanguages ? JSON.parse(localStorageLanguages) : null;
          } catch (e) {
            console.log(e);
          }
          const languages = response.data.languages;
          let i = 0;
          response.data.languages.forEach(lang => {
            const localStorageLanguage = localStorageLanguagesObjects?.find(o => o.id === lang.id);
            languages[lang.id] = lang;
            languages[lang.id].checked = localStorageLanguage ? localStorageLanguage.checked : i < 5;
            i++;
          });
          this.updateLanguages(languages);
          this.updateSourceMessages(response.data.sourceMessages);
        })
        .catch(() => {
        })
        .finally(() => {
          this.setIsLoading(false);
        });
    },
    loadColumns () {
      const localStorageColumns = localStorage.getItem('admin-translations-columns');
      let localStorageColumnsObjects = null;
      try {
        localStorageColumnsObjects = localStorageColumns ? JSON.parse(localStorageColumns) : null;
      } catch (e) {
        console.log(e);
      }
      if (localStorageColumnsObjects) {
        this.setColumns(localStorageColumnsObjects);
      }
    },
    filterSourceMessages () {
      let sourceMessages = this.sourceMessages.filter((sourceMessage) => {
        if (this.isNullOrUndefined(sourceMessage.message) || this.isNullOrUndefined(this.search)) {
          return true;
        }
        const search = this.search.toLowerCase().trim();
        for (const language of this.checkedLanguages) {
          if (sourceMessage.languages[language.id] &&
            sourceMessage.languages[language.id].toLowerCase().trim().includes(search)) {
            return true;
          }
        }
        return sourceMessage.message.toLowerCase().trim().includes(search);
      });
      if (this.emptyMessages) {
        sourceMessages = sourceMessages.filter((sourceMessage) => {
          for (const language of this.checkedLanguages) {
            if (this.isNullOrUndefined(sourceMessage.languages[language.id]) ||
              sourceMessage.languages[language.id].trim() === '') {
              return true;
            }
          }
          return false;
        });
      }
      this.setFilteredSourceMessages(sourceMessages);
    },
    change (sourceMessage, language) {
      if (this.isNullOrUndefined(sourceMessage.isModified)) {
        sourceMessage.isModified = {};
      }

      const modifiedMessages = this.modifiedMessages;

      if (this.isModified(sourceMessage, language)) {
        sourceMessage.isModified[language.id] = true;
        if (!(language.id in modifiedMessages)) {
          modifiedMessages[language.id] = {};
        }
        modifiedMessages[language.id][sourceMessage.id] = sourceMessage.languages[language.id]
          ? sourceMessage.languages[language.id]
          : '';
      } else {
        sourceMessage.isModified[language.id] = false;
        if (language.id in modifiedMessages && sourceMessage.id in modifiedMessages[language.id]) {
          delete modifiedMessages[language.id][sourceMessage.id];

          if (Object.keys(modifiedMessages[language.id]).length === 0) {
            delete modifiedMessages[language.id];
          }
        }
      }

      this.updateModifiedMessages(modifiedMessages);
    },
    isModified (sourceMessage, language) {
      let originalSourceMessage = this.originalSourceMessages[this.sourceMessages.indexOf(sourceMessage)];
      let originalValue = this.normalizeStringValue(originalSourceMessage.languages[language.id]);
      let newValue = this.normalizeStringValue(sourceMessage.languages[language.id]);
      return originalValue !== newValue;
    },
    toggleCheckedSourceMessages () {
      const checkedSourceMessages = [];
      if (this.checkedSourceMessages.length === 0) {
        for (const sourceMessage of this.displayedSourceMessages) {
          checkedSourceMessages.push(sourceMessage.id);
        }
      }
      this.setCheckedSourceMessages(checkedSourceMessages);
    },
    copyObj (obj) {
      return JSON.parse(JSON.stringify(obj));
    },
    t (str) {
      return this.$craft.t('translations-admin', str);
    },
    getNumberOfLines (str) {
      if (this.isNullOrUndefined(str)) {
        return 1;
      }
      const nbLines = str.split(/\r\n|\r|\n/).length;
      if (nbLines > 1) {
        return nbLines;
      }
      return 1;
    },
    getElementContentWidth (element) {
      const styles = window.getComputedStyle(element);
      const padding = parseFloat(styles.paddingLeft) + parseFloat(styles.paddingRight);

      return element.clientWidth - padding;
    },
    normalizeStringValue (value) {
      return this.isNullOrUndefined(value) ? '' : value.trim();
    },
    isNullOrUndefined (value) {
      return typeof value === 'undefined' || value === null;
    },
    changeSort (property) {
      if (this.sortProperty !== property) {
        this.setSortProperty(property);
        this.setSortDirection('asc');
      } else {
        this.setSortDirection(this.sortDirection === 'asc' ? 'desc' : 'asc');
      }
    },
    onSourceMessageCheckboxClick (event) {
      const checkboxes = Array.from(this.$refs["translateTable"].querySelectorAll('.source-message-checkbox'));
      const checkbox = event.target.previousSibling;
      const checkboxIndex = checkboxes.indexOf(checkbox);
      const firstCheckbox = checkboxes.find(el => el.checked);
      let lastCheckbox = null;
      if (this.lastCheckbox !== null && this.lastCheckbox !== checkbox) {
        lastCheckbox = this.lastCheckbox;
      } else if (firstCheckbox !== undefined && firstCheckbox !== checkbox) {
        lastCheckbox = firstCheckbox;
      }
      const lastCheckboxIndex = checkboxes.indexOf(lastCheckbox);
      const min = Math.min(checkboxIndex, lastCheckboxIndex);
      const max = Math.max(checkboxIndex, lastCheckboxIndex);

      if (event.shiftKey && lastCheckbox !== null) {
        this.checkedSourceMessages = [];
        for (let i = min; i <= max; i++) {
          this.checkedSourceMessages.push(this.displayedSourceMessages[i].id);
        }
      }

      if (!event.shiftKey) {
        this.lastCheckbox = !checkbox.checked ? checkbox : null;
      }
    },
    ...mapMutations({
      setIsLoading: 'setIsLoading',
      setCategory: 'setCategory',
      setLanguages: 'setLanguages',
      setSourceMessages: 'setSourceMessages',
      setFilteredSourceMessages: 'setFilteredSourceMessages',
      setEmptyMessages: 'setEmptyMessages',
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setOriginalSourceMessages: 'setOriginalSourceMessages',
      setSortProperty: 'setSortProperty',
      setSortDirection: 'setSortDirection',
      setColumns: 'setColumns',
    }),
    ...mapActions({
      updateLanguages: 'updateLanguages',
      updateSourceMessages: 'updateSourceMessages',
      updateModifiedMessages: 'updateModifiedMessages'
    })
  }
};
</script>

<style lang="scss" scoped>
@import "~craftcms-sass/mixins";

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
  width: 172px;
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

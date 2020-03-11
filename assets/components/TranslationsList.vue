<template>
    <div class="elements" :class="{'busy': isLoading || isAdding || isDeleting}">
        <table class="translate-table">
            <thead>
            <tr>
                <th class="checkbox-cell" style="width: 4%">
                    <div class="selectallcontainer">
                        <div class="btn" role="checkbox" tabindex="0" :aria-checked="ariaChecked"
                             @click="toggleCheckedSourceMessages()">
                            <div class="checkbox"
                                 :class="{
                                        'checked': checkedSourceMessages.length > 0 &&
                                            checkedSourceMessages.length === displayedSourceMessages.length,
                                        'indeterminate': checkedSourceMessages.length > 0 &&
                                            checkedSourceMessages.length !== displayedSourceMessages.length
                                     }"></div>
                        </div>
                    </div>
                </th>
                <th :style="'width: ' + (96/(languages.length + 1)) + '%'">{{ t('Key') }}</th>
                <th v-for="language in languages" v-bind:key="language.id"
                    :style="'width: ' + (96/(languages.length + 1)) + '%'">
                    {{ language.displayName }}
                </th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="sourceMessage in displayedSourceMessages" v-bind:key="sourceMessage.id"
                :class="{'sel': checkedSourceMessages.indexOf(sourceMessage.id) > -1}">
                <td class="checkbox-cell">
                    <input :id="'source-message-' + sourceMessage.id" type="checkbox" class="checkbox"
                           :title="t('Select')"
                           :value="sourceMessage.id"
                           v-model="checkedSourceMessages">
                    <label :for="'source-message-' + sourceMessage.id"></label>
                </td>

                <td>
                    <div class="mobile-only cell-label">{{ t('Key') }}</div>
                    <pre>{{ sourceMessage.message }}</pre>
                </td>

                <td v-for="language in languages" v-bind:key="language.id"
                    :class="{'modified': typeof sourceMessage.isModified !== 'undefined' &&
                            sourceMessage.isModified !== null &&
                            sourceMessage.isModified[language.id] === true}">
                    <div class="mobile-only cell-label">{{ language.displayName }}</div>
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
            </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import axios from 'axios';
import { EventBus } from '../EventBus';
import { mapActions, mapMutations, mapState } from 'vuex';

export default {
  data () {
    return {
      modifiedMessages: {}
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
    ...mapState({
      isLoading: state => state.isLoading,
      isAdding: state => state.isAdding,
      isDeleting: state => state.isDeleting,
      category: state => state.category,
      languages: state => state.languages,
      originalSourceMessages: state => state.originalSourceMessages,
      sourceMessages: state => state.sourceMessages,
      filteredSourceMessages: state => state.filteredSourceMessages,
      displayedSourceMessages: state => state.displayedSourceMessages,
      emptyMessages: state => state.emptyMessages,
      search: state => state.search,
    })
  },
  created () {
    this.changeCategory();

    EventBus.$on('translations-paginated', (translations) => {
      this.setDisplayedSourceMessages(translations);
    });

    EventBus.$on('set-page', () => {
      document.getElementById('content').scrollTop = 0;
    });

    EventBus.$on('translations-saved', () => {
      this.setOriginalSourceMessages(this.copyObj(this.sourceMessages));
      this.modifiedMessages = {};
      for (const sourceMessage of this.sourceMessages) {
        this.$set(sourceMessage, 'isModified', null);
      }
      EventBus.$emit('translations-modified', this.modifiedMessages);
    });
  },
  mounted () {
    //this.stickyElements();
  },
  watch: {
    search () {
      this.filterSourceMessages();
    },
    emptyMessages () {
      this.filterSourceMessages();
    },
    filteredSourceMessages () {
      EventBus.$emit('translations-filtered', this.filteredSourceMessages);
    },
    category () {
      this.changeCategory();
    }
  },
  methods: {
    changeCategory () {
      this.setLanguages([]);
      this.setSourceMessages([]);
      this.setFilteredSourceMessages([]);
      this.setEmptyMessages(false);
      this.setCheckedSourceMessages([]);
      this.modifiedMessages = {};
      EventBus.$emit('translations-modified', this.modifiedMessages);
      this.loadSourceMessages();
    },
    loadSourceMessages () {
      this.setIsLoading(true);

      axios
        .get(this.$craft.getActionUrl('translations-admin/messages/get-translations', { category: this.category }))
        .then((response) => {
          this.setLanguages(response.data.languages);
          this.updateSourceMessages(response.data.sourceMessages);
        })
        .catch((error) => {
          console.log(error);
        })
        .finally(() => {
          this.setIsLoading(false);
        });
    },
    filterSourceMessages () {
      let sourceMessages = this.sourceMessages.filter((sourceMessage) => {
        if (sourceMessage.message === null || this.search === null) {
          return true;
        }
        const search = this.search.toLowerCase().trim();
        for (const language of this.languages) {
          if (sourceMessage.languages[language.id] !== null &&
            sourceMessage.languages[language.id].toLowerCase().trim().includes(search)) {
            return true;
          }
        }
        return sourceMessage.message.toLowerCase().trim().includes(search);
      });
      if (this.emptyMessages) {
        sourceMessages = sourceMessages.filter((sourceMessage) => {
          for (const language of this.languages) {
            if (sourceMessage.languages[language.id] === null ||
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
      if (typeof sourceMessage.isModified === 'undefined' || sourceMessage.isModified === null) {
        this.$set(sourceMessage, 'isModified', {});
      }

      if (this.isModified(sourceMessage, language)) {
        this.$set(sourceMessage.isModified, language.id, true);
        if (!(language.id in this.modifiedMessages)) {
          this.modifiedMessages[language.id] = {};
        }
        this.modifiedMessages[language.id][sourceMessage.id] = sourceMessage.languages[language.id]
          ? sourceMessage.languages[language.id]
          : '';
      } else {
        this.$set(sourceMessage.isModified, language.id, false);
        if (language.id in this.modifiedMessages && sourceMessage.id in this.modifiedMessages[language.id]) {
          delete this.modifiedMessages[language.id][sourceMessage.id];

          if (Object.keys(this.modifiedMessages[language.id]).length === 0) {
            delete this.modifiedMessages[language.id];
          }
        }
      }

      EventBus.$emit('translations-modified', this.modifiedMessages);
    },
    isModified (sourceMessage, language) {
      let originalSourceMessage = this.originalSourceMessages[this.sourceMessages.indexOf(sourceMessage)];
      let originalValue = originalSourceMessage.languages[language.id];
      if (originalValue !== null) {
        originalValue = originalValue.trim();
      }
      let newValue = sourceMessage.languages[language.id];
      if (newValue !== null) {
        newValue = newValue.trim();
      }
      if ((originalValue === '' || originalValue === null) &&
        (newValue === '' || newValue === null)) {
        return false;
      }
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
    stickyElements () {
      const content = document.querySelector('#content');
      const contentHeader = document.querySelector('.content-header');
      const contentHeaderWrapper = document.querySelector('.content-header-wrapper');
      let stuck = false;
      const stickPoint = contentHeader.offsetTop;

      content.addEventListener('scroll', () => {
        const distance = contentHeader.offsetTop - (content.offsetTop + content.scrollTop);
        const offset = (content.offsetTop + content.scrollTop);
        if ((distance <= 0) && !stuck) {
          contentHeaderWrapper.style.height = contentHeader.clientHeight + 'px';
          contentHeader.classList.add('fixed');
          contentHeader.style.top = content.offsetTop + 'px';
          contentHeader.style.width = this.getElementContentWidth(content) + 'px';
          stuck = true;
        } else if (stuck && (offset <= stickPoint)) {
          contentHeaderWrapper.style.height = '';
          contentHeader.classList.remove('fixed');
          contentHeader.style.top = '';
          contentHeader.style.width = '';
          stuck = false;
        }
      });
    },
    copyObj (obj) {
      return JSON.parse(JSON.stringify(obj));
    },
    t (str) {
      return this.$craft.t('translations-admin', str);
    },
    getNumberOfLines (str) {
      if (str === null) {
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
    ...mapMutations({
      setIsLoading: 'setIsLoading',
      setLanguages: 'setLanguages',
      setSourceMessages: 'setSourceMessages',
      setFilteredSourceMessages: 'setFilteredSourceMessages',
      setDisplayedSourceMessages: 'setDisplayedSourceMessages',
      setEmptyMessages: 'setEmptyMessages',
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setOriginalSourceMessages: 'setOriginalSourceMessages',
    }),
    ...mapActions({
      updateSourceMessages: 'updateSourceMessages'
    })
  }
};
</script>

<style lang="scss" scoped>
@import "~craftcms-sass/mixins";

.content-header {
    background: #fff;
    margin: 0 -10px;
    padding: 7px 10px 0 10px;
}

.content-header.fixed {
    position: fixed;
    z-index: 1;
}

.toolbar {
    margin-bottom: 8px;
}

.translate-table {
    width: calc(100% + 20px);
    margin: 18px -10px 10px -10px;
    table-layout: fixed;
}

.translate-table th {
    font-weight: bold;
    color: rgba(0, 0, 0, 0.5);
    border-bottom: 1px solid #e3e5e8;
    padding: 7px 10px;
}

.translate-table th,
.translate-table td {
    background-color: #fff;
    box-sizing: border-box;
}

.translate-table td {
    padding: 5px 10px;
}

.translate-table td.checkbox-cell {
    position: relative;
    padding-bottom: 0;
}

.translate-table tr td:first-child {
    line-height: 1.2em;
}

.translate-table tr:nth-child(2n) td {
    background-color: #f8f9fa;
}

.translate-table tr:hover td {
    background-color: #f3f4f5;
}

.translate-table tr td.modified {
    background-color: #fcfbe2;
}

.translate-table tr.sel td {
    background: #d5d8dd;
}

.translate-table pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

textarea {
    overflow-x: hidden;
    min-height: 32px;
}

.message-text {
    position: relative;
}

.message-text textarea {
    padding-right: 40px;
}

.language-label {
    text-transform: lowercase;
    font-size: 11px;
    line-height: 11px;
    display: inline-block;
    position: absolute;
    top: 1px;
    right: 1px;
    background: #f7f7f7;
    color: #555e69;
    border-radius: 2px;
    padding: 2px 5px 3px;
    pointer-events: none;
}

.btn .checkbox + span {
    vertical-align: top;
    display: inline-block;
    margin-left: 5px;
}

.btn:disabled {
    pointer-events: none;
    opacity: 0.5;
}

#footer {
    z-index: 1;
    box-shadow: 0 -1px 0 rgba(0, 0, 20, 0.1);
    padding: 10px 24px;
    background: #fff;
}

.cell-label {
    margin-bottom: 4px;
    font-weight: bold;
    color: rgba(0, 0, 0, 0.5);
}

.mobile-only {
    display: none;
}

@media (max-width: 613px) {
    .mobile-only {
        display: block;
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
    }
}
</style>

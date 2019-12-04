<template>
    <div id="content-container">
        <div id="content" ref="content">
            <div class="content-header">
                <div class="toolbar">
                    <div class="flex">
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
                        <div v-show="checkedSourceMessages.length > 0">
                            <div class="btn menubtn" data-icon="settings" :title="t('Actions')"></div>
                            <div class="menu">
                                <ul>
                                    <li>
                                        <a class="error" @click="deleteMessages()">
                                            {{ t('Delete') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div v-show="checkedSourceMessages.length === 0">
                            <div class="btn menubtn statusmenubtn">
                                <span class="status" :class="{'pending': emptyMessages}"></span>
                                {{ !emptyMessages ? t('All') : t('Empty') }}
                            </div>
                            <div class="menu">
                                <ul class="padded">
                                    <li>
                                        <a :class="{'sel': !emptyMessages}"
                                           @click="emptyMessages = false">
                                            <span class="status"></span>{{ t('All') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a :class="{'sel': emptyMessages}"
                                           @click="emptyMessages = true">
                                            <span class="status pending"></span>{{ t('Empty') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div v-show="checkedSourceMessages.length === 0"
                             class="flex-grow texticon search icon clearable">
                            <input class="text fullwidth" type="text" autocomplete="off" placeholder="Search"
                                   v-model="search">
                            <div class="clear hidden" title="Clear"></div>
                        </div>
                        <div v-show="checkedSourceMessages.length === 0">
                            <input class="text" type="text" v-model="messageToAdd" :placeholder="t('Message')">
                            <button class="btn" type="button" @click="addMessage()"
                                    :disabled="messageToAdd === null || messageToAdd.trim() === ''">
                                {{ t('Add') }}
                            </button>
                        </div>
                        <div class="spinner" :class="{'invisible': !(isLoading || isAdding || isDeleting)}"></div>
                    </div>
                </div>
                <div class="translate-columns-header">
                    <div class="checkbox-cell" style="width: 4%"></div>
                    <div :style="'width: ' + (96/(languages.length + 1)) + '%'">{{ t('Key') }}</div>
                    <div v-for="language in languages" v-bind:key="language.id"
                         :style="'width: ' + (96/(languages.length + 1)) + '%'">
                        {{ language.displayName }}
                    </div>
                </div>
            </div>
            <table class="translate-table">
                <tbody>
                <tr v-for="sourceMessage in displayedSourceMessages" v-bind:key="sourceMessage.id"
                    :class="{'sel': checkedSourceMessages.indexOf(sourceMessage.id) > -1}">
                    <td class="checkbox-cell" width="4%">
                        <input :id="'source-message-' + sourceMessage.id" type="checkbox" class="checkbox"
                               :title="t('Select')"
                               :value="sourceMessage.id"
                               v-model="checkedSourceMessages">
                        <label :for="'source-message-' + sourceMessage.id"></label>
                    </td>

                    <td :width="(96/(languages.length + 1)) + '%'">
                        <div class="mobile-only cell-label">{{ t('Key') }}</div>
                        <span>{{ sourceMessage.message }}</span>
                    </td>

                    <td v-for="language in languages" v-bind:key="language.id"
                        :class="{'modified': isModified(sourceMessage, language)}"
                        :width="(96/(languages.length + 1)) + '%'">
                        <div class="mobile-only cell-label">{{ language.displayName }}</div>
                        <input class="text nicetext fullwidth" type="text"
                               v-model="sourceMessage.languages[language.id]"
                               @change="change()"
                               @keyup="change()"
                               data-show-chars-left="" autocomplete="off" placeholder="">
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div id="footer">
            <div class="translate-pagination">
                <div class="page-info">
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
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { EventBus } from '../EventBus';

export default {
  data () {
    return {
      isLoading: false,
      isAdding: false,
      isDeleting: false,
      search: '',
      emptyMessages: false,
      languages: [],
      originalSourceMessages: [],
      sourceMessages: [],
      filteredSourceMessages: [],
      checkedSourceMessages: [],
      filterSourceMessageDebounceFn: null,
      page: 1,
      perPage: 40,
      pages: [],
      messageToAdd: '',
      category: null
    };
  },
  created () {
    this.filterSourceMessageDebounceFn = this.debounce(this.filterSourceMessages, 250);

    EventBus.$on('category-changed', (cat) => {
      this.changeCategory(cat);
    });

    EventBus.$on('translations-saved', () => {
      this.originalSourceMessages = this.copyObj(this.sourceMessages);
      this.change();
    });
  },
  mounted () {
    this.stickyElements();
  },
  computed: {
    displayedSourceMessages () {
      return this.paginate(this.filteredSourceMessages);
    },
    ariaChecked () {
      if (this.checkedSourceMessages.length === 0) {
        return 'false';
      } else {
        if (this.checkedSourceMessages.length === this.displayedSourceMessages.length) {
          return 'true';
        } else {
          return 'mixed';
        }
      }
    }
  },
  watch: {
    search () {
      this.filterSourceMessageDebounceFn();
    },
    emptyMessages () {
      this.filterSourceMessages();
    },
    filteredSourceMessages () {
      this.setPages();
    }
  },
  methods: {
    changeCategory (cat) {
      this.category = cat;
      this.languages = [];
      this.sourceMessages = [];
      this.filteredSourceMessages = [];
      this.emptyMessages = false;
      this.checkedSourceMessages = [];
      this.loadSourceMessages();
      EventBus.$emit('translations-modified', null);
    },
    loadSourceMessages () {
      this.isLoading = true;

      axios
        .get('/actions/translate/translate/get-translations?category=' + this.category)
        .then((response) => {
          this.languages = response.data.languages;
          this.sourceMessages = response.data.sourceMessages;
          this.filteredSourceMessages = this.sourceMessages;
          this.originalSourceMessages = this.copyObj(this.sourceMessages);
        })
        .catch((error) => {
          console.log(error);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    updateSourceMessages (newSourceMessages) {
      this.sourceMessages = newSourceMessages;
      this.filteredSourceMessages = this.sourceMessages;
      this.originalSourceMessages = this.copyObj(this.sourceMessages);
    },
    addMessage () {
      this.isAdding = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translate/translate/add');
      formData.append('message', this.messageToAdd);
      formData.append('category', this.category);

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            EventBus.$emit('translation-added');
            this.sourceMessages.push(response.data.sourceMessage);
            this.updateSourceMessages(this.sourceMessages);
          } else {
            EventBus.$emit('translation-added-error');
          }
        })
        .catch((error) => {
          EventBus.$emit('translation-added-error');
          console.log(error);
        })
        .finally(() => {
          this.isAdding = false;
          this.messageToAdd = '';
        });
    },
    deleteMessages () {
      this.isDeleting = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translate/translate/delete');

      for (const sourceMessageId of this.checkedSourceMessages) {
        formData.append('sourceMessageId[]', sourceMessageId);
      }

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            EventBus.$emit('translation-deleted');
            const sourceMessages = this.sourceMessages.filter((sourceMessage) => {
              return this.checkedSourceMessages.indexOf(sourceMessage.id) === -1;
            });
            this.updateSourceMessages(sourceMessages);
            this.checkedSourceMessages = [];
          } else {
            EventBus.$emit('translation-deleted-error');
          }
        })
        .catch((error) => {
          EventBus.$emit('translation-deleted-error');
          console.log(error);
        })
        .finally(() => {
          this.isDeleting = false;
        });
    },
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
      this.filteredSourceMessages = sourceMessages;
    },
    change () {
      let sourceMessages = null;

      for (const sourceMessage of this.sourceMessages) {
        for (const language of this.languages) {
          if (this.isModified(sourceMessage, language)) {
            if (sourceMessages === null) {
              sourceMessages = [];
            }
            if (!(language.id in sourceMessages)) {
              sourceMessages[language.id] = [];
            }
            sourceMessages[language.id][sourceMessage.id] = sourceMessage.languages[language.id]
              ? sourceMessage.languages[language.id]
              : '';
          }
        }
      }

      EventBus.$emit('translations-modified', sourceMessages);
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
    setPage (nb) {
      this.page = nb;
      this.$refs.content.scrollTop = 0;
    },
    toggleCheckedSourceMessages () {
      if (this.checkedSourceMessages.length > 0) {
        this.checkedSourceMessages = [];
      } else {
        this.checkedSourceMessages = [];
        for (const sourceMessage of this.displayedSourceMessages) {
          this.checkedSourceMessages.push(sourceMessage.id);
        }
      }
    },
    stickyElements () {
      const content = document.querySelector('#content');
      const contentHeader = document.querySelector('.content-header');
      const translateTable = document.querySelector('.translate-table');
      let stuck = false;
      const stickPoint = contentHeader.offsetTop;

      content.addEventListener('scroll', () => {
        const distance = contentHeader.offsetTop - (content.offsetTop + content.scrollTop);
        const offset = (content.offsetTop + content.scrollTop);
        if ((distance <= 0) && !stuck) {
          contentHeader.classList.add('fixed');
          contentHeader.style.top = content.offsetTop + 'px';
          contentHeader.style.width = this.getElementContentWidth(content) + 'px';
          translateTable.style.paddingTop = contentHeader.clientHeight + 'px';
          stuck = true;
        } else if (stuck && (offset <= stickPoint)) {
          contentHeader.classList.remove('fixed');
          contentHeader.style.top = '';
          contentHeader.style.width = '';
          translateTable.style.paddingTop = '';
          stuck = false;
        }
      });
    },
    copyObj (obj) {
      return JSON.parse(JSON.stringify(obj));
    },
    t (str) {
      return this.$craft.t('app', str);
    },
    getElementContentWidth (element) {
      const styles = window.getComputedStyle(element);
      const padding = parseFloat(styles.paddingLeft) + parseFloat(styles.paddingRight);

      return element.clientWidth - padding;
    },
    debounce (func, wait, immediate) {
      let timeout;
      return function () {
        const context = this, args = arguments;
        const later = function () {
          timeout = null;
          if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
      };
    }
  }
};
</script>

<style scoped>
#main-container #main #main-content #content-container #content {
    padding-top: 14px;
}

.content-header {
    background: #fff;
    margin: 0 -10px;
    padding: 10px 10px 0 10px;
}

.content-header.fixed {
    position: fixed;
    z-index: 1;
}

.toolbar {
    margin-bottom: 8px;
}

.translate-columns-header {
    background: #fff;
    display: flex;
    margin: 0 -10px;
    padding: 0 0 10px 0;
    font-weight: bold;
    color: rgba(0, 0, 0, 0.5);
    border-bottom: 1px dotted #e3e5e8;
}

.translate-columns-header > * {
    flex-shrink: 0;
    flex-grow: 0;
    box-sizing: border-box;
}

.translate-columns-header > * {
    padding: 0 10px;
}

.translate-table {
    width: calc(100% + 20px);
    margin: 0 -10px 10px -10px;
    table-layout: fixed;
}

.translate-table td {
    padding: 5px 10px;
    background-color: #fff;
    box-sizing: border-box;
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

.translate-table tr.sel td:not(.checkbox-cell) {
    background: #d5d8dd;
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

.translate-pagination {
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

    .translate-columns-header {
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

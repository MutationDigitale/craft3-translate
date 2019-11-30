<template>
    <div id="content-container">
        <div id="content" ref="content">
            <div class="content-header">
                <div class="toolbar">
                    <div class="flex">
                        <div class="flex-grow texticon search icon clearable">
                            <input class="text fullwidth" type="text" autocomplete="off" placeholder="Search"
                                   v-model="search">
                            <div class="clear hidden" title="Clear"></div>
                        </div>
                        <div>
                            <div class="btn" role="checkbox" tabindex="0"
                                 :aria-checked="emptyMessages ? 'true' : 'false'"
                                 @click="toggleEmptyMessages()">
                                <div class="checkbox" :class="{'checked': emptyMessages}"></div>
                                <span>{{ t('Empty translations') }}</span>
                            </div>
                        </div>
                        <div>
                            <input class="text" type="text" v-model="messageToAdd" :placeholder="t('Message')">
                            <button class="btn" type="button" @click="addMessage()"
                                    :disabled="messageToAdd === null || messageToAdd.trim() === ''">
                                {{ t('Add') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="translate-columns-header">
                    <div :style="'width: ' + (94/(languages.length + 1)) + '%'">{{ t('Key') }}</div>
                    <div v-for="language in languages" v-bind:key="language.id"
                         :style="'width: ' + (94/(languages.length + 1)) + '%'">
                        {{ language.displayName }}
                    </div>
                    <div style="width: 6%">{{ t('Actions') }}</div>
                </div>
            </div>
            <table class="translate-table">
                <tbody>
                <tr v-for="sourceMessage in displayedSourceMessages" v-bind:key="sourceMessage.id">
                    <td :width="(94/(languages.length + 1)) + '%'">
                        <span>{{ sourceMessage.message }}</span>
                    </td>

                    <td v-for="language in languages" v-bind:key="language.id"
                        :class="{'modified': isModified(sourceMessage, language)}"
                        :width="(94/(languages.length + 1)) + '%'">
                        <input class="text nicetext fullwidth" type="text"
                               v-model="sourceMessage.languages[language.id]"
                               @change="change()"
                               @keyup="change()"
                               data-show-chars-left="" autocomplete="off" placeholder="">
                    </td>

                    <td width="6%">
                        <button type="button" class="btn" data-icon="trash"
                                @click="deleteMessage(sourceMessage.id)"></button>
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

                <div v-if="pages.length > 1">
                    <button class="btn page-link" type="button"
                            :disabled="page === 1"
                            @click="setPage(1)">«
                    </button>
                    <button class="btn page-link" type="button" data-icon="leftangle"
                            :disabled="page === 1"
                            @click="setPage(page-1)"></button>

                    <button class="btn page-link" type="button"
                            :class="{'active': pageNumber === page}"
                            v-for="pageNumber in pages.slice(page < 5 ? 0 : page - 5, page+5)"
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
  props: {
    category: String
  },
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
      filterSourceMessageDebounceFn: null,
      page: 1,
      perPage: 40,
      pages: [],
      messageToAdd: '',
    };
  },
  mounted () {
    this.filterSourceMessageDebounceFn = this.debounce(this.filterSourceMessages, 250);

    this.getTranslations();

    EventBus.$on('translations-saved', () => {
      this.originalSourceMessages = this.copyObj(this.sourceMessages);
      this.change();
    });

    this.stickyElements();
  },
  computed: {
    displayedSourceMessages () {
      return this.paginate(this.filteredSourceMessages);
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
    getTranslations () {
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
            this.getTranslations();
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
    deleteMessage (messageId) {
      this.isDeleting = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translate/translate/delete');
      formData.append('sourceMessageId', messageId);

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            EventBus.$emit('translation-deleted');
            this.getTranslations();
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
    toggleEmptyMessages () {
      this.emptyMessages = !this.emptyMessages;
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
    padding-top: 12px;
}

.content-header {
    background: #fff;
    margin: 0 -12px;
    padding: 12px;
}

.content-header.fixed {
    position: fixed;
    z-index: 1;
}

.toolbar {
    margin-bottom: 12px;
}

.toolbar .flex:not(.flex-nowrap) > * {
    margin-bottom: 0;
}

.translate-columns-header {
    background: #fff;
}

.translate-columns-header {
    display: flex;
    margin: 0 -12px;
    font-weight: bold;
}

.translate-columns-header > * {
    flex-shrink: 0;
    flex-grow: 0;
    box-sizing: border-box;
}

.translate-columns-header > * {
    padding: 0 12px;
}

.translate-table {
    width: calc(100% + 24px);
    margin: 0 -12px 12px -12px;
    table-layout: fixed;
}

.translate-table td {
    padding: 6px 12px;
    background-color: #fff;
    box-sizing: border-box;
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
    padding: 8px 0;
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
</style>

<template>
    <div id="content-container">
        <div id="content">
            <div class="content-header">
                <div class="toolbar">
                    <div class="flex">
                        <div class="flex-grow texticon search icon clearable">
                            <input class="text fullwidth" type="text" autocomplete="off" placeholder="Search"
                                   v-model="search">
                            <div class="clear hidden" title="Clear"></div>
                        </div>
                    </div>
                </div>
                <div class="translate-columns-header">
                    <div>Key</div>
                    <div v-for="language in languages" v-bind:key="language.id">
                        {{ language.displayName }}
                    </div>
                </div>
            </div>
            <table class="translate-table">
                <tbody>
                <tr v-for="sourceMessage in displayedSourceMessages" v-bind:key="sourceMessage.id">
                    <td>
                        <span>{{ sourceMessage.message }}</span>
                    </td>

                    <td v-for="language in languages" v-bind:key="language.id"
                        :class="{'modified': isModified(sourceMessage, language)}">
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
            <div class="pagination">
                <div class="page-info">
                    {{(page-1)*perPage}}-{{((page-1)*perPage)+displayedSourceMessages.length}} {{t('translations')}} /
                    {{filteredSourceMessages.length}}
                </div>

                <div v-if="pages.length > 1">
                    <button class="btn page-link" type="button"
                            :disabled="page === 1" @click="page = 1">«
                    </button>
                    <button class="btn page-link" type="button" data-icon="leftangle"
                            :disabled="page === 1" @click="page--"></button>

                    <button class="btn page-link" type="button"
                            :class="{'active': pageNumber === page}"
                            v-for="pageNumber in pages.slice(page < 5 ? 0 : page - 5, page+5)"
                            v-bind:key="pageNumber"
                            @click="page = pageNumber">
                        {{pageNumber}}
                    </button>

                    <button class="btn page-link" type="button" data-icon="rightangle"
                            :disabled="page === pages.length" @click="page++"></button>
                    <button class="btn page-link" type="button"
                            :disabled="page === pages.length" @click="page = pages.length">»
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
      isSaving: false,
      search: '',
      languages: [],
      originalSourceMessages: [],
      sourceMessages: [],
      page: 1,
      perPage: 40,
      pages: []
    };
  },
  mounted () {
    this.getTranslations();

    EventBus.$on('translations-saved', () => {
      this.originalSourceMessages = this.copyObj(this.sourceMessages);
    });

    this.stickyElements();
  },
  computed: {
    filteredSourceMessages () {
      return this.sourceMessages.filter((sourceMessage) => {
        if (sourceMessage.message === null || this.search === null) return true;
        return sourceMessage.message.toLowerCase().trim().includes(this.search.toLowerCase().trim());
      });
    },
    displayedSourceMessages () {
      return this.paginate(this.filteredSourceMessages);
    }
  },
  watch: {
    filteredSourceMessages () {
      this.setPages();
    }
  },
  methods: {
    getTranslations: function () {
      this.isLoading = true;

      axios
        .get('/actions/translate/translate/get-translations')
        .then((response) => {
          this.languages = response.data.languages;
          this.sourceMessages = response.data.sourceMessages;
          this.originalSourceMessages = this.copyObj(this.sourceMessages);
        })
        .catch((error) => {
          console.log(error);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    setPages () {
      let numberOfPages = Math.ceil(this.filteredSourceMessages.length / this.perPage);
      this.pages = [];
      for (let index = 1; index <= numberOfPages; index++) {
        this.pages.push(index);
      }
    },
    paginate (sourceMessages) {
      let page = this.page;
      let perPage = this.perPage;
      let from = (page * perPage) - perPage;
      let to = (page * perPage);
      return sourceMessages.slice(from, to);
    },
    change: function () {
      let sourceMessages = null;

      this.sourceMessages.forEach((sourceMessage) => {
        this.languages.forEach((language) => {
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
        });
      });

      EventBus.$emit('translations-modified', sourceMessages);
    },
    isModified: function (sourceMessage, language) {
      const originalSourceMessage = this.originalSourceMessages.find(obj => {
        return obj.id === sourceMessage.id;
      });
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
    stickyElements: function () {
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
    copyObj: function (obj) {
      return JSON.parse(JSON.stringify(obj));
    },
    t: function (str) {
      return this.$craft.t('app', str);
    },
    getElementContentWidth: function (element) {
      const styles = window.getComputedStyle(element);
      const padding = parseFloat(styles.paddingLeft) + parseFloat(styles.paddingRight);

      return element.clientWidth - padding;
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
    flex-grow: 1;
    flex-basis: 0;
}

.translate-columns-header > * {
    padding: 0 12px;
}

.translate-table {
    width: calc(100% + 24px);
    margin: 0 -12px 12px -12px;
}

.translate-table td,
.translate-table th {
    padding: 6px 12px;
    background-color: #fff;
    width: 33.3333%;
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

.pagination {
    padding: 8px 0;
    display: flex;
    align-items: center;
}

.pagination .page-info {
    margin-right: auto;
    padding: 6px 0;
}

.pagination .page-link {
    margin-left: 12px;
}

.pagination .page-link.active {
    pointer-events: none;
    background: rgba(0, 0, 20, 0.1);
}

.pagination .page-link:disabled {
    pointer-events: none;
    opacity: 0.5;
}
</style>

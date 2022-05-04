<template>
  <div id="translations-toolbar" class="flex flex-grow">
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
      <div class="btn menubtn statusmenubtn">{{ t('Languages') }}</div>
      <div class="menu">
        <ul class="padded checkbox-menu">
          <li v-for="language in languages" :key="language.id" class="checkbox-menu-item">
            <input :id="`language-checkbox-${language.id}`"
                   class="checkbox"
                   type="checkbox"
                   :checked="languages[language.id].checked"
                   @input="updateLanguages(language.id, $event.target.checked)">
            <label :for="`language-checkbox-${language.id}`">
              <span>{{ language.displayName }}</span>
              <span class="light" v-if="language.nativeName"> – {{ language.nativeName }}</span>
            </label>
          </li>
        </ul>
      </div>
    </div>
    <div v-show="checkedSourceMessages.length === 0">
      <div class="btn menubtn statusmenubtn"><span class="status" :class="{'pending': emptyMessages}"></span>{{
          !emptyMessages ? t('All') : t('Empty')
        }}</div>
      <div class="menu">
        <ul class="padded">
          <li>
            <a :class="{'sel': !emptyMessages}"
               @click="setEmptyMessages(false)">
              <span class="status"></span>{{ t('All') }}
            </a>
          </li>
          <li>
            <a :class="{'sel': emptyMessages}"
               @click="setEmptyMessages(true)">
              <span class="status pending"></span>{{ t('Empty') }}
            </a>
          </li>
        </ul>
      </div>
    </div>
    <div v-show="checkedSourceMessages.length === 0"
         class="flex-grow texticon search icon clearable search-container">
      <input class="text fullwidth" type="text" autocomplete="off" placeholder="Search"
             v-model="search">
      <div class="clear hidden" title="Clear"></div>
    </div>
    <div v-show="checkedSourceMessages.length === 0" class="textarea-container">
            <textarea class="text" rows="1"
                      v-model="messageToAdd" :placeholder="t('Message')"></textarea>
    </div>
    <div v-show="checkedSourceMessages.length === 0">
      <button class="btn" type="button" @click="addMessage()"
              :disabled="messageToAdd === null || messageToAdd.trim() === ''">
        {{ t('Add') }}
      </button>
    </div>
  </div>
</template>

<script>
import {mapActions, mapMutations, mapState} from 'vuex';
import axios from 'axios';

export default {
  data() {
    return {
      messageToAdd: ''
    };
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
    ...mapState({
      isAdding: state => state.isAdding,
      isDeleting: state => state.isDeleting,
      languages: state => state.languages,
      category: state => state.category,
      sourceMessages: state => state.sourceMessages,
      emptyMessages: state => state.emptyMessages,
      checkedSourceMessages: state => state.checkedSourceMessages
    })
  },
  methods: {
    t(str) {
      return this.$craft.t('translations-admin', str);
    },
    updateLanguages(languageId, value) {
      const languages = this.languages;
      languages[languageId].checked = value;
      this.setLanguages(languages);
    },
    addMessage() {
      this.setIsAdding(true);

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translations-admin/messages/add');
      formData.append('message', this.messageToAdd);
      formData.append('category', this.category);

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            this.emitter.emit('translation-added');
            const sourceMessages = this.sourceMessages;
            sourceMessages.push(response.data.sourceMessage);
            this.updateSourceMessages(sourceMessages);
          } else {
            this.emitter.emit('translation-added-error');
          }
        })
        .catch(() => {
          this.emitter.emit('translation-added-error');
        })
        .finally(() => {
          this.setIsAdding(false);
          this.messageToAdd = '';
        });
    },
    deleteMessages() {
      this.setIsDeleting(true);

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translations-admin/messages/delete');

      for (const sourceMessageId of this.checkedSourceMessages) {
        formData.append('sourceMessageId[]', sourceMessageId);
      }

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            this.emitter.emit('translation-deleted');
            const sourceMessages = this.sourceMessages.filter((sourceMessage) => {
              return this.checkedSourceMessages.indexOf(sourceMessage.id) === -1;
            });
            this.updateSourceMessages(sourceMessages);
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
    ...mapMutations({
      setIsAdding: 'setIsAdding',
      setIsDeleting: 'setIsDeleting',
      setLanguages: 'setLanguages',
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setSearch: 'setSearch',
      setEmptyMessages: 'setEmptyMessages'
    }),
    ...mapActions({
      updateSourceMessages: 'updateSourceMessages'
    })
  }
};
</script>

<style lang="scss" scoped>
@import "~craftcms-sass/mixins";

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

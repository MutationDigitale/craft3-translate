<template>
  <div id="translations-toolbar" class="flex flex-grow">
    <div>
      <button class="btn menubtn statusmenubtn"><span class="status" :class="{'pending': emptyMessages}"></span>{{
          !emptyMessages ? t('All') : t('Empty')
        }}</button>
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
    <div class="flex-grow texticon search icon search-container">
      <input class="text fullwidth" type="text" autocomplete="off" placeholder="Search"
             v-model="search">
    </div>
    <div>
      <button class="btn menubtn" data-icon="sliders">{{ t('Columns') }}</button>
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
      <textarea class="text" rows="1" v-model="messageToAdd" :placeholder="t('Message')"></textarea>
    </div>
    <div v-if="addPermission">
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
  props: {
    addPermission: Boolean,
  },
  data() {
    return {
      messageToAdd: ''
    };
  },
  mounted () {

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
      columns: state => state.columns,
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
    ...mapMutations({
      setIsAdding: 'setIsAdding',
      setCheckedSourceMessages: 'setCheckedSourceMessages',
      setSearch: 'setSearch',
      setEmptyMessages: 'setEmptyMessages'
    }),
    ...mapActions({
      updateLanguages: 'updateLanguages',
      updateColumns: 'updateColumns',
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

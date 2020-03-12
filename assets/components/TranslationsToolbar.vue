<template>
    <div class="flex flex-grow flex-nowrap">
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
            <div class="btn menubtn statusmenubtn"><span class="status" :class="{'pending': emptyMessages}"></span>{{
                !emptyMessages ? t('All') : t('Empty') }}</div>
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
import { mapActions, mapMutations, mapState } from 'vuex';
import axios from 'axios';
import { EventBus } from '../EventBus';

export default {
  data () {
    return {
      messageToAdd: ''
    };
  },
  computed: {
    search: {
      get () {
        return this.$store.state.search;
      },
      set (value) {
        this.setSearch(value);
      }
    },
    ...mapState({
      isAdding: state => state.isAdding,
      isDeleting: state => state.isDeleting,
      category: state => state.category,
      sourceMessages: state => state.sourceMessages,
      emptyMessages: state => state.emptyMessages,
      checkedSourceMessages: state => state.checkedSourceMessages
    })
  },
  methods: {
    t (str) {
      return this.$craft.t('translations-admin', str);
    },
    addMessage () {
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
            EventBus.$emit('translation-added');
            const sourceMessages = this.sourceMessages;
            sourceMessages.push(response.data.sourceMessage);
            this.updateSourceMessages(sourceMessages);
          } else {
            EventBus.$emit('translation-added-error');
          }
        })
        .catch((error) => {
          EventBus.$emit('translation-added-error');
          console.log(error);
        })
        .finally(() => {
          this.setIsAdding(false);
          this.messageToAdd = '';
        });
    },
    deleteMessages () {
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
            EventBus.$emit('translation-deleted');
            const sourceMessages = this.sourceMessages.filter((sourceMessage) => {
              return this.checkedSourceMessages.indexOf(sourceMessage.id) === -1;
            });
            this.updateSourceMessages(sourceMessages);
            this.setCheckedSourceMessages([]);
          } else {
            EventBus.$emit('translation-deleted-error');
          }
        })
        .catch((error) => {
          EventBus.$emit('translation-deleted-error');
          console.log(error);
        })
        .finally(() => {
          this.setIsDeleting(false);
        });
    },
    ...mapMutations({
      setIsAdding: 'setIsAdding',
      setIsDeleting: 'setIsDeleting',
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

.search-container {
    flex: 2;
}

.textarea-container {
    height: 34px;
    flex: 1;
}

textarea {
    overflow-x: hidden;
    min-height: 34px;
    height: 34px;
    width: 100%;
}
</style>

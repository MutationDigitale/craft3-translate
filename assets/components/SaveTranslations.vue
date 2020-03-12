<template>
    <div>
        <button type="button" class="btn submit" @click="save()"
                :disabled="!isModified || isSaving">
            <span v-if="isSaving">{{ t('Saving') }}...</span>
            <span v-else>{{ t('Save') }}</span>
        </button>
    </div>
</template>

<script>
import axios from 'axios';
import { EventBus } from './../EventBus.js';
import { mapState } from 'vuex';

export default {
  data () {
    return {
      isSaving: false,
      isModified: false,
    };
  },
  computed: {
    ...mapState({
      modifiedMessages: state => state.modifiedMessages,
      modifiedMessagesKeys: state => state.modifiedMessagesKeys,
    })
  },
  watch: {
    modifiedMessagesKeys () {
      this.isModified = this.modifiedMessagesKeys.length > 0;
    }
  },
  mounted () {
    window.addEventListener('keydown', (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        if (this.isModified && !this.isSaving) {
          this.save();
        }
        event.preventDefault();
      }
    });
  },
  methods: {
    save: function () {
      this.isSaving = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translations-admin/messages/save');

      for (const languageId in this.modifiedMessages) {
        for (const sourceMessageId in this.modifiedMessages[languageId]) {
          const message = this.modifiedMessages[languageId][sourceMessageId];
          formData.append('translations[' + languageId + '][' + sourceMessageId + ']', message);
        }
      }

      axios
        .post('', formData)
        .then((response) => {
          if (response.data.success) {
            EventBus.$emit('translations-saved');
          } else {
            EventBus.$emit('translations-saved-error');
          }
        })
        .catch((error) => {
          EventBus.$emit('translations-saved-error');
          console.log(error);
        })
        .finally(() => {
          this.isSaving = false;
        });
    },
    t: function (str) {
      return this.$craft.t('translations-admin', str);
    }
  }
};
</script>

<style scoped>
.btn:disabled {
    opacity: 0.5;
    pointer-events: none;
}
</style>

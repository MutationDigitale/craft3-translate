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

export default {
  data () {
    return {
      isSaving: false,
      isModified: false,
      sourceMessageInputs: null,
    };
  },
  mounted () {
    EventBus.$on('translations-modified', (sourceMessageInputs) => {
      this.sourceMessageInputs = sourceMessageInputs;
      this.isModified = sourceMessageInputs !== null && Object.keys(sourceMessageInputs).length > 0;
    });
  },
  methods: {
    save: function () {
      this.isSaving = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translate/translate/save');

      for (const languageId in this.sourceMessageInputs) {
        for (const sourceMessageId in this.sourceMessageInputs[languageId]) {
          const message = this.sourceMessageInputs[languageId][sourceMessageId];
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
      return this.$craft.t('app', str);
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

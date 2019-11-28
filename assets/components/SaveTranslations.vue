<template>
    <div>
        <button type="button" class="btn submit" @click="save()"
                :disabled="sourceMessageInputs === null || isSaving">
            <span v-if="isSaving">Saving...</span>
            <span v-else>Save</span>
        </button>
    </div>
</template>

<script>
import axios from 'axios';
import { EventBus } from "./../EventBus.js";

export default {
  data () {
    return {
      isSaving: false,
      sourceMessageInputs: null,
    };
  },
  mounted () {
    EventBus.$on('translations-modified', (sourceMessageInputs) => {
      this.sourceMessageInputs = sourceMessageInputs;
    });
  },
  methods: {
    save: function () {
      this.isSaving = true;

      const formData = new FormData();

      formData.append(this.$csrfTokenName, this.$csrfTokenValue);
      formData.append('action', 'translate/translate/save');

      for (const languageId in this.sourceMessageInputs) {
        this.sourceMessageInputs[languageId].forEach((message, sourceMessageId) => {
          formData.append('translations[' + languageId + '][' + sourceMessageId + ']', message);
        });
      }

      axios
        .post('', formData)
        .then(() => {
          EventBus.$emit('translations-saved');
        })
        .catch((error) => {
          console.log(error);
        })
        .finally(() => {
          this.isSaving = false;
        });
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

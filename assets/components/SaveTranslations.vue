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
import { clone, postAction } from '../helpers';
import { mapGetters, mapState } from 'vuex';

export default {
  data() {
    return {
      isSaving: false,
    };
  },
  computed: {
    ...mapState({
      modifiedMessages: state => state.modifiedMessages,
    }),
    ...mapGetters({
      isModified: 'isModified',
    })
  },
  mounted() {
    window.addEventListener('keydown', this.onKeydown);
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.onKeydown);
  },
  methods: {
    onKeydown: function (event) {
      // The key is "S" with caps lock, but the shortcuts with Shift are left to the browser
      if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key?.toLowerCase() === 's') {
        if (this.isModified && !this.isSaving) {
          this.save();
        }
        event.preventDefault();
      }
    },
    save: function () {
      this.isSaving = true;

      // Copy the sent translations, they can still be changed during the request
      const savedMessages = clone(this.modifiedMessages);

      postAction(this, 'translations-admin/messages/save', { translations: savedMessages })
        .then((response) => {
          if (response.data.success) {
            this.$craft.cp.displayNotice(this.t('Translations saved'));
            this.emitter.emit('translations-saved', savedMessages);
          } else {
            this.$craft.cp.displayError(this.t('Translations not saved'));
          }
        })
        .catch(() => {
          this.$craft.cp.displayError(this.t('Translations not saved'));
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

<style lang="scss" scoped>
.btn:disabled {
  opacity: 0.5;
  pointer-events: none;
}
</style>

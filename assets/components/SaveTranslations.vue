<template>
  <div>
    <button type="button" class="btn submit" @click="save()"
            :disabled="!isModified || isSaving">
      <span v-if="isSaving">{{ t('Saving') }}...</span>
      <span v-else>{{ t('Save') }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useStore } from 'vuex';
import { clone, postAction, useCraft } from '../helpers';

const store = useStore();
const { globals, craft, emitter, t } = useCraft();

const isSaving = ref(false);
const isModified = computed(() => store.getters.isModified);

function save () {
  isSaving.value = true;

  // Copy the sent translations, they can still be changed during the request
  const savedMessages = clone(store.state.modifiedMessages);

  postAction(globals, 'translations-admin/messages/save', { translations: savedMessages })
    .then((response) => {
      if (response.data.success) {
        craft.cp.displayNotice(t('Translations saved'));
        emitter.emit('translations-saved', savedMessages);
      } else {
        craft.cp.displayError(t('Translations not saved'));
      }
    })
    .catch(() => {
      craft.cp.displayError(t('Translations not saved'));
    })
    .finally(() => {
      isSaving.value = false;
    });
}

function onKeydown (event) {
  // The key is "S" with caps lock, but the shortcuts with Shift are left to the browser
  if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key?.toLowerCase() === 's') {
    if (isModified.value && !isSaving.value) {
      save();
    }
    event.preventDefault();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<style lang="scss" scoped>
.btn:disabled {
  opacity: 0.5;
  pointer-events: none;
}
</style>

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
const { craft, garnish, emitter, t } = useCraft();

const isSaving = ref(false);
const isModified = computed(() => store.getters.isModified);

function save () {
  isSaving.value = true;

  // Copy the sent translations, they can still be changed during the request
  const savedMessages = clone(store.state.modifiedMessages);

  postAction(craft, 'translations-admin/messages/save', { translations: savedMessages })
    .then((response) => {
      if (response.data.success) {
        craft.cp.displaySuccess(t('Translations saved'));
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

// Ctrl+S (Cmd+S on a Mac), like the save shortcut of the other control panel pages: the shortcuts of Craft only work on the
// top layer (e.g. not while a modal is open), and the browser doesn't open its "Save page as" dialog
const saveShortcut = { keyCode: garnish.S_KEY, ctrl: true };
let shortcutLayer = null;

function onSaveShortcut () {
  if (isModified.value && !isSaving.value) {
    save();
  }
}

onMounted(() => {
  shortcutLayer = garnish.uiLayerManager.layer;
  garnish.uiLayerManager.registerShortcut(saveShortcut, onSaveShortcut, shortcutLayer);
});
onBeforeUnmount(() => garnish.uiLayerManager.unregisterShortcut(saveShortcut, shortcutLayer));
</script>

<style lang="scss" scoped>
.btn:disabled {
  opacity: 0.5;
  pointer-events: none;
}
</style>

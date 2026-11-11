import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';
import SaveTranslations from '../../assets/components/SaveTranslations.vue';
import { store } from '../../assets/store/TranslationsStore';
import { mountComponent } from './helpers';

async function mountSave (modifiedMessages) {
  const mounted = mountComponent(SaveTranslations);
  await store.dispatch('updateModifiedMessages', modifiedMessages);
  await flushPromises();

  return mounted;
}

function pressKey (init) {
  window.dispatchEvent(new KeyboardEvent('keydown', { cancelable: true, ...init }));
}

describe('the save button', () => {
  it('sends the translations as JSON, which keeps their line breaks', async () => {
    axios.post.mockResolvedValue({ data: { success: true, deletedIds: [] } });
    const { wrapper, emitter } = await mountSave({ fr: { 1: 'Ligne 1\nLigne 2' } });
    const saved = vi.fn();
    emitter.on('translations-saved', saved);

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(axios.post).toHaveBeenCalledWith(
      '/actions/translations-admin/messages/save',
      { translations: { fr: { 1: 'Ligne 1\nLigne 2' } } },
      { headers: { 'X-CSRF-Token': 'csrf-token' } }
    );
    expect(saved).toHaveBeenCalledWith({ fr: { 1: 'Ligne 1\nLigne 2' } });
  });

  it('saves with Ctrl+S, also with caps lock, but leaves the shortcuts with Shift to the browser', async () => {
    axios.post.mockReturnValue(new Promise(() => {}));
    await mountSave({ fr: { 1: 'x' } });

    pressKey({ key: 'S', ctrlKey: true, shiftKey: true });
    expect(axios.post).not.toHaveBeenCalled();

    pressKey({ key: 'S', ctrlKey: true });
    expect(axios.post).toHaveBeenCalledTimes(1);
  });


  it('keeps the changes when the translations are not saved', async () => {
    axios.post.mockRejectedValue(new Error('Network Error'));
    const { wrapper, craft } = await mountSave({ fr: { 1: 'x' } });

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(craft.cp.displayError).toHaveBeenCalledWith('Translations not saved');
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'x' } });
    expect(wrapper.find('button').element.disabled).toBe(false);
  });
});

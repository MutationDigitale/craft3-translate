import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';
import SaveTranslations from '../../assets/components/SaveTranslations.vue';
import { store } from '../../assets/store/TranslationsStore';
import { items, mountComponent, setSourceMessages } from './helpers';

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

  it('removes the messages deleted in the meantime from the list', async () => {
    setSourceMessages(items(3));
    axios.post.mockResolvedValue({ data: { success: true, deletedIds: [2] } });
    const { wrapper, emitter } = await mountSave({ fr: { 1: 'Un', 2: 'Deux' } });
    const deleted = vi.fn();
    emitter.on('messages-deleted-meanwhile', deleted);

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(store.state.sourceMessages.map(s => s.id)).toEqual([1, 3]);
    expect(store.state.originalSourceMessages.map(s => s.id)).toEqual([1, 3]);
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'Un' } });
    expect(deleted).toHaveBeenCalled();
  });

  it('keeps the changes when the translations are not saved', async () => {
    axios.post.mockRejectedValue(new Error('Network Error'));
    const { wrapper, emitter } = await mountSave({ fr: { 1: 'x' } });
    const failed = vi.fn();
    emitter.on('translations-saved-error', failed);

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(failed).toHaveBeenCalled();
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'x' } });
    expect(wrapper.find('button').element.disabled).toBe(false);
  });
});

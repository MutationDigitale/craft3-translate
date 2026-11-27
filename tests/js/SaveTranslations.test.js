import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import SaveTranslations from '../../assets/components/SaveTranslations.vue';
import { store } from '../../assets/store/TranslationsStore';
import { mountComponent, sendActionRequest } from './helpers';

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
    sendActionRequest.mockResolvedValue({ data: { success: true, deletedIds: [] } });
    const { wrapper, emitter } = await mountSave({ fr: { 1: 'Ligne 1\nLigne 2' } });
    const saved = vi.fn();
    emitter.on('translations-saved', saved);

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(sendActionRequest).toHaveBeenCalledWith(
      'POST',
      'translations-admin/messages/save',
      { data: { translations: { fr: { 1: 'Ligne 1\nLigne 2' } } } }
    );
    expect(saved).toHaveBeenCalledWith({ fr: { 1: 'Ligne 1\nLigne 2' } });
  });

  it('saves with Ctrl+S, also with caps lock, but leaves the shortcuts with Shift to the browser', async () => {
    sendActionRequest.mockReturnValue(new Promise(() => {}));
    await mountSave({ fr: { 1: 'x' } });

    pressKey({ key: 'S', ctrlKey: true, shiftKey: true });
    expect(sendActionRequest).not.toHaveBeenCalled();

    pressKey({ key: 'S', ctrlKey: true });
    expect(sendActionRequest).toHaveBeenCalledTimes(1);
  });


  it('keeps the changes when the translations are not saved', async () => {
    sendActionRequest.mockRejectedValue(new Error('Network Error'));
    const { wrapper, craft } = await mountSave({ fr: { 1: 'x' } });

    await wrapper.find('button').trigger('click');
    await flushPromises();

    expect(craft.cp.displayError).toHaveBeenCalledWith('Translations not saved');
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'x' } });
    expect(wrapper.find('button').element.disabled).toBe(false);
  });
});

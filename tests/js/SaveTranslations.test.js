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

describe('the save button', () => {
  it('sends the translations as JSON, which keeps their line breaks', async () => {
    sendActionRequest.mockResolvedValue({ data: { success: true, deletedIds: [] } });
    const { wrapper, craft, emitter } = await mountSave({ fr: { 1: 'Ligne 1\nLigne 2' } });
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
    // The success style of the notifications of Craft
    expect(craft.cp.displaySuccess).toHaveBeenCalledWith('Translations saved');
  });

  it('saves with the Ctrl+S shortcut of Craft, but not with Shift', async () => {
    sendActionRequest.mockReturnValue(new Promise(() => {}));
    const { garnish } = await mountSave({ fr: { 1: 'x' } });

    expect(garnish.uiLayerManager.registerShortcut).toHaveBeenCalledWith({ keyCode: 83, ctrl: true }, expect.any(Function), 0);

    garnish.uiLayerManager.press({ keyCode: 83, ctrl: true, shift: true });
    expect(sendActionRequest).not.toHaveBeenCalled();

    garnish.uiLayerManager.press({ keyCode: 83, ctrl: true });
    expect(sendActionRequest).toHaveBeenCalledTimes(1);

    // Not again while saving
    garnish.uiLayerManager.press({ keyCode: 83, ctrl: true });
    expect(sendActionRequest).toHaveBeenCalledTimes(1);
  });

  it('does not save without changes, and removes the shortcut when it is unmounted', async () => {
    const { wrapper, garnish } = await mountSave({});

    garnish.uiLayerManager.press({ keyCode: 83, ctrl: true });
    expect(sendActionRequest).not.toHaveBeenCalled();

    wrapper.unmount();
    expect(garnish.uiLayerManager.shortcuts).toEqual([]);
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

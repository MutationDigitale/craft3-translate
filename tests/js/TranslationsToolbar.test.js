import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';
import TranslationsToolbar from '../../assets/components/TranslationsToolbar.vue';
import { store } from '../../assets/store/TranslationsStore';
import { mountComponent, sourceMessage } from './helpers';

async function add (wrapper, message) {
  await wrapper.find('textarea').setValue(message);
  await wrapper.find('button.btn:not(.menubtn)').trigger('click');
  await flushPromises();
}

describe('adding a message', () => {
  it('sends the message as JSON and adds it to the list', async () => {
    store.commit('setCategory', 'site');
    axios.post.mockResolvedValue({ data: { success: true, sourceMessage: sourceMessage(1, 'Line 1\nLine 2') } });
    const { wrapper } = mountComponent(TranslationsToolbar, { addPermission: true });

    await add(wrapper, 'Line 1\nLine 2');

    expect(axios.post).toHaveBeenCalledWith(
      '/actions/translations-admin/messages/add',
      { message: 'Line 1\nLine 2', category: 'site' },
      { headers: { 'X-CSRF-Token': 'csrf-token' } }
    );
    expect(store.state.sourceMessages.map(s => s.message)).toEqual(['Line 1\nLine 2']);
    expect(wrapper.find('textarea').element.value).toBe('');
  });

  it('keeps the message when it is not added, and shows why', async () => {
    store.commit('setCategory', 'site');
    axios.post.mockResolvedValue({ data: { success: false, error: 'This message already exists in the category.' } });
    const { wrapper, emitter } = mountComponent(TranslationsToolbar, { addPermission: true });
    const failed = vi.fn();
    emitter.on('translation-added-error', failed);

    await add(wrapper, 'Existing');

    expect(failed).toHaveBeenCalledWith('This message already exists in the category.');
    expect(wrapper.find('textarea').element.value).toBe('Existing');

    axios.post.mockRejectedValue(new Error('Network Error'));
    await add(wrapper, 'Offline');
    expect(wrapper.find('textarea').element.value).toBe('Offline');
    expect(store.state.isAdding).toBe(false);
  });
});

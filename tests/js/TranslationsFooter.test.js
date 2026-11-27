import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import TranslationsFooter from '../../assets/components/TranslationsFooter.vue';
import { store } from '../../assets/store/TranslationsStore';
import { items, mountComponent, sendActionRequest, setSourceMessages, sourceMessage } from './helpers';

const permissions = { addPermission: true, deletePermission: true, exportPermission: true };
const button = (wrapper, label) => wrapper.findAll('button').find(b => b.text().includes(label));

describe('the footer', () => {
  it('asks to confirm how many messages are deleted, and deletes them', async () => {
    setSourceMessages(items(3));
    store.commit('setCheckedSourceMessages', [1, 3]);
    sendActionRequest.mockResolvedValue({ data: { success: true } });
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const { wrapper, craft } = mountComponent(TranslationsFooter, permissions);

    await button(wrapper, 'Delete').trigger('click');
    await flushPromises();

    expect(craft.t).toHaveBeenCalledWith('translations-admin', expect.stringContaining('{count, plural'), { count: 2 });
    expect(confirm).toHaveBeenCalled();
    expect(sendActionRequest).toHaveBeenCalledWith(
      'POST',
      'translations-admin/messages/delete',
      { data: { sourceMessageId: [1, 3] } }
    );
    expect(store.state.sourceMessages.map(s => s.id)).toEqual([2]);
    expect(store.state.checkedSourceMessages).toEqual([]);
    expect(craft.t).toHaveBeenCalledWith('translations-admin', '{count, plural, =1{Translation deleted} other{# translations deleted}}', { count: 2 });
    expect(craft.cp.displayNotice).toHaveBeenCalled();
  });

  it('copies the saved translations, not the unsaved changes', async () => {
    store.commit('setCategory', 'site');
    setSourceMessages([sourceMessage(1, 'Hello', { fr: 'Bonjour' }), sourceMessage(2, 'Bye')]);
    store.state.sourceMessages[0].languages.fr = 'Unsaved change';
    store.commit('setCheckedSourceMessages', [1]);
    sendActionRequest.mockResolvedValue({ data: { success: true, sourceMessages: [sourceMessage(5, 'Hello', { fr: 'Bonjour' })] } });
    const { wrapper } = mountComponent(TranslationsFooter, permissions);

    await button(wrapper, 'Copy translations').trigger('click');
    store.commit('setCheckedSourceMessages', []);
    store.commit('setCategory', 'other');
    await flushPromises();
    await button(wrapper, 'Paste translations').trigger('click');
    await flushPromises();

    expect(sendActionRequest).toHaveBeenCalledWith(
      'POST',
      'translations-admin/messages/add-multiple',
      { data: { category: 'other', sourceMessages: [{ message: 'Hello', languages: { en: '', fr: 'Bonjour' } }] } }
    );
  });

  it('tells how many messages were already in the category when pasting', async () => {
    store.commit('setCategory', 'site');
    setSourceMessages(items(2));
    store.commit('setCheckedSourceMessages', [1, 2]);
    sendActionRequest.mockResolvedValue({ data: { success: true, sourceMessages: [sourceMessage(5, 'Item 001')] } });
    const { wrapper, craft } = mountComponent(TranslationsFooter, permissions);

    await button(wrapper, 'Copy translations').trigger('click');
    store.commit('setCheckedSourceMessages', []);
    store.commit('setCategory', 'other');
    await flushPromises();
    await button(wrapper, 'Paste translations').trigger('click');
    await flushPromises();

    expect(craft.t).toHaveBeenCalledWith('translations-admin', expect.stringContaining('already in the category'), { count: 1 });
    expect(craft.cp.displayNotice).toHaveBeenCalledWith(expect.stringContaining('Translations pasted, except'));
  });

  it('exports the whole category, or the selected messages', async () => {
    store.commit('setCategory', 'site');
    setSourceMessages([sourceMessage(1, 'Item 10'), sourceMessage(2, 'Item 2'), sourceMessage(3, 'Other')]);
    const { wrapper, craft } = mountComponent(TranslationsFooter, permissions);
    const exportedIds = async () => {
      craft.downloadFromUrl.mockClear();
      await button(wrapper, 'Export').trigger('click');
      await flushPromises();
      return craft.downloadFromUrl.mock.calls[0][2].sourceMessageId;
    };

    expect(await exportedIds()).toBeUndefined();
    expect(craft.downloadFromUrl.mock.calls[0][2]).toEqual({ CRAFT_CSRF_TOKEN: 'csrf-token', category: 'site' });

    store.commit('setCheckedSourceMessages', [2, 1]);
    expect(await exportedIds()).toEqual([2, 1]);
  });


  it('shows an error when the export fails', async () => {
    store.commit('setCategory', 'site');
    setSourceMessages(items(1));
    const { wrapper, craft } = mountComponent(TranslationsFooter, permissions);
    craft.downloadFromUrl.mockRejectedValue(new Error('Forbidden'));

    await button(wrapper, 'Export').trigger('click');
    await flushPromises();

    expect(craft.cp.displayError).toHaveBeenCalledWith('Translations could not be exported');
  });

  it('paginates the messages', async () => {
    setSourceMessages(items(130));
    const { wrapper } = mountComponent(TranslationsFooter, permissions);
    const pageNumbers = () => wrapper.findAll('.page-number').map(b => b.text());

    expect(pageNumbers()).toEqual(['1', '2', '3', '4']);
    expect(wrapper.find('.page-first').element.disabled).toBe(true);

    await wrapper.find('.page-last').trigger('click');
    expect(store.state.page).toBe(4);
    expect(pageNumbers()).toEqual(['2', '3', '4']);
    expect(wrapper.find('.page-number.active').attributes('aria-current')).toBe('page');

    // Fewer messages: back to the new last page
    store.commit('setFilteredSourceMessages', store.state.sourceMessages.slice(0, 50));
    await flushPromises();
    expect(store.state.page).toBe(2);
    expect(wrapper.find('.page-info').text()).toContain('41-50');
  });
});

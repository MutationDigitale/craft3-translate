import { describe, expect, it } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import TranslationsToolbar from '../../assets/components/TranslationsToolbar.vue';
import { store } from '../../assets/store/TranslationsStore';
import { mountComponent, sendActionRequest, setSourceMessages, sourceMessage } from './helpers';

async function add (wrapper, message) {
  await wrapper.find('textarea').setValue(message);
  await wrapper.find('button.btn:not(.menubtn)').trigger('click');
  await flushPromises();
}

describe('adding a message', () => {
  it('sends the message as JSON and adds it to the list', async () => {
    store.commit('setCategory', 'site');
    sendActionRequest.mockResolvedValue({ data: { success: true, sourceMessage: sourceMessage(1, 'Line 1\nLine 2') } });
    const { wrapper } = mountComponent(TranslationsToolbar, { addPermission: true });

    await add(wrapper, 'Line 1\nLine 2');

    expect(sendActionRequest).toHaveBeenCalledWith(
      'POST',
      'translations-admin/messages/add',
      { data: { message: 'Line 1\nLine 2', category: 'site' } }
    );
    expect(store.state.sourceMessages.map(s => s.message)).toEqual(['Line 1\nLine 2']);
    expect(wrapper.find('textarea').element.value).toBe('');
  });

  it('keeps the message when it is not added, and shows why', async () => {
    store.commit('setCategory', 'site');
    sendActionRequest.mockResolvedValue({ data: { success: false, error: 'This message already exists in the category.' } });
    const { wrapper, craft } = mountComponent(TranslationsToolbar, { addPermission: true });

    await add(wrapper, 'Existing');

    expect(craft.cp.displayError).toHaveBeenCalledWith('This message already exists in the category.');
    expect(wrapper.find('textarea').element.value).toBe('Existing');

    sendActionRequest.mockRejectedValue(new Error('Network Error'));
    await add(wrapper, 'Offline');
    expect(wrapper.find('textarea').element.value).toBe('Offline');
    expect(store.state.isAdding).toBe(false);
  });
});

describe('the filter menu', () => {
  it('has an Empty filter for any language and for each language', async () => {
    setSourceMessages([]);
    const { wrapper } = mountComponent(TranslationsToolbar, { addPermission: true });
    const options = () => wrapper.findAll('.menu').at(0).findAll('a');

    expect(options().map(a => a.text())).toEqual(['All', 'Empty in any language', 'Empty in English', 'Empty in French']);
    await options()[1].trigger('click');
    expect(store.state.emptyMessages).toBe(true);
    expect(wrapper.find('.statusmenubtn').text()).toBe('Empty in any language');

    // Only the chosen option is selected
    await options()[3].trigger('click');

    expect(store.state.emptyMessages).toBe('fr');
    expect(options().map(a => a.classes().includes('sel'))).toEqual([false, false, false, true]);
    expect(wrapper.find('.statusmenubtn').text()).toBe('Empty in French');

    await options()[0].trigger('click');
    expect(store.state.emptyMessages).toBe(false);
    expect(wrapper.find('.statusmenubtn').text()).toBe('All');
  });
});

describe('the columns menu', () => {
  it('shows or hides a language or the date column, and remembers it', async () => {
    setSourceMessages([]);
    const languagesBefore = store.state.languages;
    const { wrapper } = mountComponent(TranslationsToolbar, { addPermission: true });

    await wrapper.find('#language-checkbox-fr').setValue(false);
    await wrapper.find('#column-checkbox-dateCreated').setValue(false);

    expect(store.state.languages.map(language => language.checked)).toEqual([true, false]);
    expect(languagesBefore[1].checked).toBe(true);
    expect(store.state.columns.dateCreated.checked).toBe(false);
    expect(JSON.parse(localStorage.getItem('admin-translations-languages'))[1].checked).toBe(false);
    expect(JSON.parse(localStorage.getItem('admin-translations-columns')).dateCreated.checked).toBe(false);
  });
});

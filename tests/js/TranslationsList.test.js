import { describe, expect, it } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';
import TranslationsList from '../../assets/components/TranslationsList.vue';
import { clone } from '../../assets/helpers';
import { store } from '../../assets/store/TranslationsStore';
import { items, languages, mountComponent, sourceMessage } from './helpers';

async function mountList (sourceMessages, { savePermission = true } = {}) {
  axios.get.mockResolvedValue({ data: { languages: structuredClone(languages), sourceMessages } });
  const mounted = mountComponent(TranslationsList, { currentCategory: 'site', savePermission });
  await flushPromises();

  return mounted;
}

// The textarea of a message's translation in the displayed page
function textarea (wrapper, key, languageId) {
  const row = wrapper.findAll('tbody tr').find(tr => tr.find('pre').text() === key);
  return row.find(`td:nth-child(${languageId === 'en' ? 3 : 4}) textarea`);
}

// Like a paste with the mouse or a correction of the spell checker: only an input event, no keyup nor change until the focus leaves
async function input (textarea, value) {
  textarea.element.value = value;
  await textarea.trigger('input');
}

const displayedKeys = wrapper => wrapper.findAll('tbody pre').map(pre => pre.text());
const checkedIds = () => [...store.state.checkedSourceMessages].sort((a, b) => a - b);

describe('the translations list', () => {
  it('loads the messages of the category', async () => {
    const { wrapper } = await mountList(items(3, i => ({ fr: `Élément ${i}` })));

    expect(axios.get).toHaveBeenCalledWith('/actions/translations-admin/messages/get-translations?category=site');
    expect(displayedKeys(wrapper)).toEqual(['Item 001', 'Item 002', 'Item 003']);
    expect(textarea(wrapper, 'Item 002', 'fr').element.value).toBe('Élément 2');
  });

  it('detects the changes made without typing (e.g. pasted with the mouse or corrected by the spell checker)', async () => {
    const { wrapper } = await mountList(items(2));

    await input(textarea(wrapper, 'Item 001', 'fr'), 'Collé');

    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'Collé' } });
    expect(textarea(wrapper, 'Item 001', 'fr').element.closest('td').classList).toContain('modified');

    await input(textarea(wrapper, 'Item 001', 'fr'), '');
    expect(store.state.modifiedMessages, 'Back to the saved value').toEqual({});
  });

  it('compares the line breaks saved as \\r\\n with the ones of the textareas', async () => {
    const { wrapper } = await mountList([sourceMessage(1, 'Multi-line', { fr: 'Ligne 1\r\nLigne 2' })]);

    // The browser gives the value of a textarea with \n
    await textarea(wrapper, 'Multi-line', 'fr').setValue('Ligne 1\nLigne 2');
    expect(store.state.modifiedMessages).toEqual({});

    await textarea(wrapper, 'Multi-line', 'fr').setValue('Ligne 1\nLigne 2 modifiée');
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'Ligne 1\nLigne 2 modifiée' } });
  });

  it('keeps the changes made while the translations were being saved', async () => {
    const { wrapper, emitter } = await mountList(items(1));
    await textarea(wrapper, 'Item 001', 'fr').setValue('Sent');
    const sent = clone(store.state.modifiedMessages);
    await textarea(wrapper, 'Item 001', 'fr').setValue('Sent, then changed');

    emitter.emit('translations-saved', sent);

    expect(store.state.originalSourceMessages[0].languages.fr).toBe('Sent');
    expect(store.state.modifiedMessages).toEqual({ fr: { 1: 'Sent, then changed' } });
  });

  it('marks the saved translations by message, after other messages were removed', async () => {
    const { wrapper, emitter } = await mountList(items(3));
    await textarea(wrapper, 'Item 003', 'fr').setValue('Trois');
    await store.dispatch('removeSourceMessages', [1]);

    emitter.emit('translations-saved', { fr: { 3: 'Trois' } });

    expect(store.state.originalSourceMessages.find(s => s.id === 3).languages.fr).toBe('Trois');
    expect(store.state.modifiedMessages).toEqual({});
  });

  it('makes the translations read-only without the save permission', async () => {
    const { wrapper } = await mountList(items(1), { savePermission: false });

    expect(textarea(wrapper, 'Item 001', 'fr').attributes()).toHaveProperty('readonly');
  });

  it('goes back to the first page when the search, filters, sort or category change', async () => {
    const { wrapper } = await mountList(items(100));
    const page = () => store.state.page;

    store.commit('setPage', 3);
    store.commit('setSearch', 'Item 0');
    await flushPromises();
    expect(page()).toBe(1);
    expect(displayedKeys(wrapper)[0]).toBe('Item 001');

    store.commit('setPage', 2);
    store.commit('setEmptyMessages', true);
    await flushPromises();
    expect(page()).toBe(1);

    store.commit('setPage', 2);
    await wrapper.find('th.orderable button').trigger('click');
    expect(page()).toBe(1);

    store.commit('setPage', 2);
    store.commit('setCategory', 'other');
    await flushPromises();
    expect(page()).toBe(1);
  });

  it('ignores the case and accents in the search', async () => {
    const { wrapper } = await mountList([
      sourceMessage(1, 'Element key'),
      sourceMessage(2, 'Other', { fr: 'Élément traduit' }),
      sourceMessage(3, 'Unrelated'),
    ]);

    store.commit('setSearch', ' ÉLEMENT ');
    await flushPromises();
    expect(displayedKeys(wrapper)).toEqual(['Element key', 'Other']);
  });

  it('only keeps the selected messages that are displayed', async () => {
    const { wrapper } = await mountList(items(50));
    const header = wrapper.find('th.checkbox-cell');

    await header.trigger('click');
    expect(store.state.checkedSourceMessages).toHaveLength(40);
    expect(header.attributes('aria-checked')).toBe('true');

    // The selected messages of the first page can't be deleted from the second one
    store.commit('setPage', 2);
    await flushPromises();
    expect(store.state.checkedSourceMessages).toEqual([]);
    expect(header.attributes('aria-checked')).toBe('false');

    // Nor once a search hides them
    store.commit('setPage', 1);
    await flushPromises();
    await header.trigger('click');
    store.commit('setSearch', 'Item 00');
    await flushPromises();
    expect(checkedIds()).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    expect(header.attributes('aria-checked')).toBe('true');
  });

  it('shows the select all checkbox as mixed when only some messages are selected', async () => {
    const { wrapper } = await mountList(items(3));

    await wrapper.find('#source-message-2').setValue(true);

    expect(wrapper.find('th.checkbox-cell').attributes('aria-checked')).toBe('mixed');
    expect(wrapper.find('th.checkbox-cell .checkbox').classes()).toContain('indeterminate');
  });

  it('selects all the messages with the keyboard', async () => {
    const { wrapper } = await mountList(items(3));
    const header = wrapper.find('th.checkbox-cell');

    await header.trigger('keydown', { key: ' ' });
    expect(checkedIds()).toEqual([1, 2, 3]);

    await header.trigger('keydown', { key: 'Enter' });
    expect(checkedIds()).toEqual([]);
  });
});

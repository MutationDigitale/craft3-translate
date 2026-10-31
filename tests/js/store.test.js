import { describe, expect, it } from 'vitest';
import { store } from '../../assets/store/TranslationsStore';
import { items, setSourceMessages, sourceMessage } from './helpers';

const keys = sourceMessages => sourceMessages.map(s => s.message);

describe('the store', () => {
  it('sorts the numbers in the keys by value', () => {
    setSourceMessages([sourceMessage(1, 'Item 10'), sourceMessage(2, 'Item 2'), sourceMessage(3, 'item 1'), sourceMessage(4, 'Élément')]);

    expect(keys(store.getters.sortedSourceMessages)).toEqual(['Élément', 'item 1', 'Item 2', 'Item 10']);

    store.commit('setSortDirection', 'desc');
    expect(keys(store.getters.sortedSourceMessages)).toEqual(['Item 10', 'Item 2', 'item 1', 'Élément']);
  });

  it('sorts by date', () => {
    setSourceMessages([
      sourceMessage(1, 'B', {}, '2026-03-01 10:00:00'),
      sourceMessage(2, 'A', {}, '2026-01-01 09:00:00'),
      sourceMessage(3, 'C', {}, '2026-01-01 10:00:00'),
    ]);
    store.commit('setSortProperty', 'dateCreated');

    expect(keys(store.getters.sortedSourceMessages)).toEqual(['A', 'C', 'B']);
  });

  it('displays a page of the sorted messages', () => {
    setSourceMessages(items(45).reverse());

    expect(store.getters.displayedSourceMessages).toHaveLength(40);
    expect(store.getters.displayedSourceMessages[0].message).toBe('Item 001');

    store.commit('setPage', 2);
    expect(keys(store.getters.displayedSourceMessages)).toEqual(['Item 041', 'Item 042', 'Item 043', 'Item 044', 'Item 045']);
  });

  it('keeps the saved translations of the remaining messages when some are removed', async () => {
    setSourceMessages(items(3, i => ({ fr: `Élément ${i}` })));
    store.state.sourceMessages[2].languages.fr = 'Unsaved';

    await store.dispatch('removeSourceMessages', [1]);

    expect(store.state.sourceMessages.map(s => s.id)).toEqual([2, 3]);
    expect(store.state.originalSourceMessages.map(s => s.id)).toEqual([2, 3]);
    expect(store.state.sourceMessages[1].languages.fr).toBe('Unsaved');
    expect(store.state.originalSourceMessages[1].languages.fr).toBe('Élément 3');
  });

  it('forgets the changes of messages in one language or all of them', async () => {
    await store.dispatch('updateModifiedMessages', { en: { 1: 'a', 2: 'b' }, fr: { 1: 'c' } });

    await store.dispatch('forgetModifiedMessages', { sourceMessageIds: [1], languageId: 'fr' });
    expect(store.state.modifiedMessages).toEqual({ en: { 1: 'a', 2: 'b' } });

    await store.dispatch('forgetModifiedMessages', { sourceMessageIds: [1, 2] });
    expect(store.state.modifiedMessages).toEqual({});
    expect(store.state.modifiedMessagesKeys).toEqual([]);
  });
});

import { describe, expect, it } from 'vitest';
import TranslationsMenu from '../../assets/components/TranslationsMenu.vue';
import { store } from '../../assets/store/TranslationsStore';
import { mountComponent } from './helpers';

describe('the categories menu', () => {
  it('links to the categories and marks the current one', async () => {
    store.commit('setCategory', 'site');
    const { wrapper } = mountComponent(TranslationsMenu, { categories: ['site', 'site.forms'] });
    const links = wrapper.findAll('a');

    expect(links.map(a => a.attributes('href'))).toEqual(['/admin/translations-admin/site', '/admin/translations-admin/site.forms']);
    expect(links.map(a => a.attributes('aria-current'))).toEqual(['page', undefined]);

    await links[1].trigger('click');
    expect(store.state.category).toBe('site.forms');
    expect(links.map(a => a.attributes('aria-current'))).toEqual([undefined, 'page']);
  });

  it('leaves the links opened in a new tab to the browser', async () => {
    store.commit('setCategory', 'site');
    const { wrapper } = mountComponent(TranslationsMenu, { categories: ['site', 'emails'] });

    await wrapper.findAll('a')[1].trigger('click', { ctrlKey: true });
    expect(store.state.category).toBe('site');
  });
});

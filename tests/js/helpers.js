import { mount } from '@vue/test-utils';
import mitt from 'mitt';
import { vi } from 'vitest';
import { clone } from '../../assets/helpers';
import { store } from '../../assets/store/TranslationsStore';

const initialState = clone(store.state);

export const languages = [
  { id: 'en', displayName: 'English', nativeName: false },
  { id: 'fr', displayName: 'French', nativeName: 'français' },
];

export function resetStore () {
  store.replaceState(clone(initialState));
}

// The parts of window.Craft used by the components
export function createCraft () {
  return {
    t: vi.fn((category, message) => message),
    getActionUrl: vi.fn((action, params) => `/actions/${action}` + (params ? '?' + new URLSearchParams(params) : '')),
    cp: { displayNotice: vi.fn(), displayError: vi.fn() },
    downloadFromUrl: vi.fn(() => Promise.resolve()),
  };
}

export function mountComponent (component, props = {}) {
  const craft = createCraft();
  const emitter = mitt();
  const wrapper = mount(component, {
    props,
    attachTo: document.getElementById('main'),
    global: {
      plugins: [store],
      mocks: {
        $craft: craft,
        $csrfTokenName: 'CRAFT_CSRF_TOKEN',
        $csrfTokenValue: 'csrf-token',
        emitter,
      },
    },
  });

  return { wrapper, craft, emitter };
}

export function sourceMessage (id, message, translations = {}, dateCreated = '2026-01-01 00:00:00') {
  return { id, message, dateCreated, languages: { en: '', fr: '', ...translations } };
}

// The messages of a category, as if they were loaded and filtered by the list
export function setSourceMessages (sourceMessages) {
  store.dispatch('updateLanguages', clone(languages).map(language => ({ ...language, checked: true })));
  store.dispatch('updateSourceMessages', sourceMessages);
  store.commit('setFilteredSourceMessages', [...store.state.sourceMessages]);
}

// Messages named "Item 001", "Item 002"…
export function items (count, translations = () => ({})) {
  return Array.from({ length: count }, (_, i) => sourceMessage(i + 1, `Item ${String(i + 1).padStart(3, '0')}`, translations(i + 1)));
}

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

// Craft.sendActionRequest(), shared by the mounted components so the tests can set its response before mounting
export const sendActionRequest = vi.fn();

// The parts of window.Craft used by the components
export function createCraft () {
  return {
    csrfTokenName: 'CRAFT_CSRF_TOKEN',
    csrfTokenValue: 'csrf-token',
    sendActionRequest,
    // With the simple params (e.g. "{language}"), not the plural ones
    t: vi.fn((category, message, params = {}) => message.replace(/\{(\w+)\}/g, (match, name) => params[name] ?? match)),
    getCpUrl: vi.fn(path => `/admin/${path}`),
    getActionUrl: vi.fn((action, params) => `/actions/${action}` + (params ? '?' + new URLSearchParams(params) : '')),
    cp: { displayNotice: vi.fn(), displaySuccess: vi.fn(), displayError: vi.fn() },
    downloadFromUrl: vi.fn(() => Promise.resolve()),
  };
}

// The parts of window.Garnish used by the components: the shortcuts of the UI layers, matched like Garnish (Ctrl is Cmd on a Mac)
export function createGarnish () {
  const shortcuts = [];
  return {
    S_KEY: 83,
    uiLayerManager: {
      layer: 0,
      shortcuts,
      registerShortcut: vi.fn((shortcut, callback, layer) => shortcuts.push({ shortcut, callback, layer })),
      unregisterShortcut: vi.fn((shortcut, layer) => {
        const index = shortcuts.findIndex(s => s.layer === layer && JSON.stringify(s.shortcut) === JSON.stringify(shortcut));
        if (index !== -1) {
          shortcuts.splice(index, 1);
        }
      }),
      // Like a keydown on the page: the shortcut with this key and the same modifiers
      press ({ keyCode, ctrl = false, shift = false, alt = false }) {
        shortcuts.find(({ shortcut }) => shortcut.keyCode === keyCode && !!shortcut.ctrl === ctrl && !!shortcut.shift === shift && !!shortcut.alt === alt)?.callback();
      },
    },
  };
}

export function mountComponent (component, props = {}) {
  const craft = createCraft();
  const garnish = createGarnish();
  const emitter = mitt();
  const wrapper = mount(component, {
    props,
    attachTo: document.getElementById('main'),
    global: {
      plugins: [store],
      // Like main.js: the components read them from the app config (useCraft())
      config: {
        globalProperties: {
          $craft: craft,
          $garnish: garnish,
          emitter,
        },
      },
    },
  });

  return { wrapper, craft, garnish, emitter };
}

export function sourceMessage (id, message, translations = {}, dateCreated = '2026-01-01 00:00:00') {
  return { id, message, dateCreated, languages: { en: '', fr: '', ...translations } };
}

// The messages of a category, as if they were loaded and filtered by the list
export function setSourceMessages (sourceMessages) {
  store.dispatch('updateLanguages', languages.map(language => ({ ...language, checked: true })));
  store.dispatch('updateSourceMessages', sourceMessages);
  store.commit('setFilteredSourceMessages', [...store.state.sourceMessages]);
}

// Messages named "Item 001", "Item 002"…
export function items (count, translations = () => ({})) {
  return Array.from({ length: count }, (_, i) => sourceMessage(i + 1, `Item ${String(i + 1).padStart(3, '0')}`, translations(i + 1)));
}

import axios from 'axios';
import { getCurrentInstance } from 'vue';

// Deep copies a JSON value (not structuredClone(), which fails on the reactive proxies of Vue)
export function clone (value) {
  return JSON.parse(JSON.stringify(value));
}

// Returns a JSON value saved in the local storage, or null if there's none or it's invalid
export function readLocalStorage (key) {
  try {
    return JSON.parse(localStorage.getItem(key));
  } catch {
    return null;
  }
}

// Returns a text to search in, or to search for: without case nor accents (e.g. "Café" becomes "cafe")
export function searchable (value) {
  return (value ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}

// Sends an action request as JSON (form data would send the line breaks of the translations as \r\n), with the globals of
// useCraft()
export function postAction (globals, action, data) {
  return axios.post(globals.$craft.getActionUrl(action), data, {
    headers: { 'X-CSRF-Token': globals.$csrfTokenValue },
  });
}

// The globals of the app (see main.js), for the components: Craft, jQuery, the CSRF token and the events emitter
export function useCraft () {
  const globals = getCurrentInstance().appContext.config.globalProperties;

  return {
    globals,
    craft: globals.$craft,
    jquery: globals.$jquery,
    emitter: globals.emitter,
    // Translates a text of the plugin
    t: (message, params = {}) => globals.$craft.t('translations-admin', message, params),
  };
}

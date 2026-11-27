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

// Sends an action request with Craft (with the CSRF token). The data is sent as JSON: form data would send the line breaks
// of the translations as \r\n.
export function postAction (craft, action, data) {
  return craft.sendActionRequest('POST', action, { data });
}

// The globals of the app (see main.js), for the components: Craft, jQuery and the events emitter
export function useCraft () {
  const globals = getCurrentInstance().appContext.config.globalProperties;

  return {
    craft: globals.$craft,
    jquery: globals.$jquery,
    emitter: globals.emitter,
    // Translates a text of the plugin
    t: (message, params = {}) => globals.$craft.t('translations-admin', message, params),
  };
}

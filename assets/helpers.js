import axios from 'axios';

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

// Sends an action request of a component as JSON (form data would send the line breaks of the translations as \r\n)
export function postAction (vm, action, data) {
  return axios.post(vm.$craft.getActionUrl(action), data, {
    headers: { 'X-CSRF-Token': vm.$csrfTokenValue },
  });
}

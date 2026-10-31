import axios from 'axios';

// Deep copies a JSON value (not structuredClone(), which fails on the reactive proxies of Vue)
export function clone (value) {
  return JSON.parse(JSON.stringify(value));
}

// Sends an action request of a component as JSON (form data would send the line breaks of the translations as \r\n)
export function postAction (vm, action, data) {
  return axios.post(vm.$craft.getActionUrl(action), data, {
    headers: { 'X-CSRF-Token': vm.$csrfTokenValue },
  });
}

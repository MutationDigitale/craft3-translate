// Deep copies a JSON value (not structuredClone(), which fails on the reactive proxies of Vue)
export function clone (value) {
  return JSON.parse(JSON.stringify(value));
}

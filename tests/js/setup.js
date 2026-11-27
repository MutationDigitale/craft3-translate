import { afterEach, beforeEach, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import { resetStore } from './helpers';

enableAutoUnmount(afterEach);

beforeEach(() => {
  resetStore();
  localStorage.clear();
  vi.clearAllMocks();
  // The element of the control panel the list scrolls to when the page changes
  document.body.innerHTML = '<div id="main"></div>';
});

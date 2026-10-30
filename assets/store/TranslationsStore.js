import { createStore } from 'vuex';
import { clone } from '../helpers';

export const store = createStore({
  state: {
    isLoading: false,
    isAdding: false,
    isDeleting: false,
    category: null,
    languages: [],
    columns: {
      dateCreated: { key: 'dateCreated', name: 'Date Created', checked: true },
    },
    originalSourceMessages: [],
    sourceMessages: [],
    filteredSourceMessages: [],
    page: 1,
    perPage: 40,
    checkedSourceMessages: [],
    modifiedMessages: {},
    modifiedMessagesKeys: [],
    search: '',
    emptyMessages: false,
    sortProperty: 'message',
    sortDirection: 'asc'
  },
  getters: {
    displayedSourceMessages: state => {
      let page = state.page;
      let perPage = state.perPage;
      let from = (page * perPage) - perPage;
      let to = (page * perPage);
      // Sort a copy, getters must not mutate the state
      return [...state.filteredSourceMessages]
        .sort((a, b) => {
          const valueA = a[state.sortProperty];
          const valueB = b[state.sortProperty];
          const result = String(valueA ?? '').localeCompare(String(valueB ?? ''));
          return state.sortDirection === 'asc' ? result : -result;
        })
        .slice(from, to);
    }
  },
  mutations: {
    setIsLoading (state, value) {
      state.isLoading = value;
    },
    setIsAdding (state, value) {
      state.isAdding = value;
    },
    setIsDeleting (state, value) {
      state.isDeleting = value;
    },
    setCategory (state, value) {
      state.category = value;
    },
    setLanguages (state, value) {
      state.languages = value;
    },
    setOriginalSourceMessages (state, value) {
      state.originalSourceMessages = value;
    },
    setSourceMessages (state, value) {
      state.sourceMessages = value;
    },
    setFilteredSourceMessages (state, value) {
      state.filteredSourceMessages = value;
    },
    setPage (state, value) {
      state.page = value;
    },
    setPerPage (state, value) {
      state.perPage = value;
    },
    setCheckedSourceMessages (state, value) {
      state.checkedSourceMessages = value;
    },
    setModifiedMessages (state, value) {
      state.modifiedMessages = value;
    },
    setModifiedMessagesKeys (state, value) {
      state.modifiedMessagesKeys = value;
    },
    setSearch (state, value) {
      state.search = value;
    },
    setEmptyMessages (state, value) {
      state.emptyMessages = value;
    },
    setSortProperty (state, value) {
      state.sortProperty = value;
    },
    setSortDirection (state, value) {
      state.sortDirection = value;
    },
    setColumns (state, value) {
      state.columns = value;
    },
  },
  actions: {
    updateColumns ({ commit }, columns) {
      localStorage.setItem('admin-translations-columns', JSON.stringify(columns));
      commit('setColumns', columns);
    },
    updateLanguages ({ commit }, languages) {
      localStorage.setItem('admin-translations-languages', JSON.stringify(languages));
      commit('setLanguages', languages);
    },
    // The original messages are the saved values, used to know which translations are modified
    updateSourceMessages ({ commit }, newSourceMessages) {
      commit('setSourceMessages', newSourceMessages);
      commit('setOriginalSourceMessages', clone(newSourceMessages));
    },
    // Unlike updateSourceMessages, the other messages keep their unsaved changes
    addSourceMessages ({ commit, state }, addedSourceMessages) {
      commit('setSourceMessages', [...state.sourceMessages, ...addedSourceMessages]);
      commit('setOriginalSourceMessages', [...state.originalSourceMessages, ...clone(addedSourceMessages)]);
    },
    removeSourceMessages ({ commit, state }, sourceMessageIds) {
      const kept = state.sourceMessages.map(sourceMessage => !sourceMessageIds.includes(sourceMessage.id));
      commit('setSourceMessages', state.sourceMessages.filter((sourceMessage, index) => kept[index]));
      commit('setOriginalSourceMessages', state.originalSourceMessages.filter((sourceMessage, index) => kept[index]));
    },
    updateModifiedMessages ({ commit }, newModifiedMessages) {
      commit('setModifiedMessages', newModifiedMessages);
      commit('setModifiedMessagesKeys', Object.keys(newModifiedMessages));
    },
    // Forgets the unsaved changes of messages, in every language or only in one
    forgetModifiedMessages ({ dispatch, state }, { sourceMessageIds, languageId = null }) {
      const modifiedMessages = {};
      for (const [modifiedLanguageId, messages] of Object.entries(state.modifiedMessages)) {
        const kept = { ...messages };
        if (languageId === null || modifiedLanguageId === languageId) {
          for (const sourceMessageId of sourceMessageIds) {
            delete kept[sourceMessageId];
          }
        }
        if (Object.keys(kept).length > 0) {
          modifiedMessages[modifiedLanguageId] = kept;
        }
      }
      dispatch('updateModifiedMessages', modifiedMessages);
    },
  },
});

import Vuex from 'vuex';
import Vue from 'vue';

Vue.use(Vuex);

export default new Vuex.Store({
  state: {
    isLoading: false,
    isAdding: false,
    isDeleting: false,
    category: null,
    languages: [],
    originalSourceMessages: [],
    sourceMessages: [],
    filteredSourceMessages: [],
    page: 1,
    perPage: 40,
    displayedSourceMessages: [],
    checkedSourceMessages: [],
    modifiedMessages: {},
    modifiedMessagesKeys: [],
    search: '',
    emptyMessages: false,
  },
  getters: {
    displayedSourceMessages: state => {
      let page = state.page;
      let perPage = state.perPage;
      let from = (page * perPage) - perPage;
      let to = (page * perPage);
      return state.filteredSourceMessages.slice(from, to);
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
    modifiedMessagesKeys (state, value) {
      state.modifiedMessagesKeys = value;
    },
    setSearch (state, value) {
      state.search = value;
    },
    setEmptyMessages (state, value) {
      state.emptyMessages = value;
    },
  },
  actions: {
    updateSourceMessages ({ commit }, newSourceMessages) {
      commit('setSourceMessages', newSourceMessages);
      commit('setFilteredSourceMessages', newSourceMessages);
      commit('setOriginalSourceMessages', JSON.parse(JSON.stringify(newSourceMessages)));
    },
    updateModifiedMessages ({ commit }, newModifiedMessages) {
      commit('modifiedMessages', newModifiedMessages);
      commit('modifiedMessagesKeys', Object.keys(newModifiedMessages));
    },
  },
});

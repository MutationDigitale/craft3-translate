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
    displayedSourceMessages: [],
    checkedSourceMessages: [],
    search: '',
    emptyMessages: false,
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
    setDisplayedSourceMessages (state, value) {
      state.displayedSourceMessages = value;
    },
    setCheckedSourceMessages (state, value) {
      state.checkedSourceMessages = value;
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
  },
});

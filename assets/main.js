import 'es6-promise/auto';

import Vue from 'vue';

import { EventBus } from './EventBus.js';

import TranslationsStore from './store/TranslationsStore';

import SaveTranslations from './components/SaveTranslations.vue';
import TranslationsList from './components/TranslationsList.vue';
import TranslationsToolbar from './components/TranslationsToolbar.vue';
import TranslationsMenu from './components/TranslationsMenu.vue';
import TranslationsFooter from './components/TranslationsFooter.vue';
import { mapState } from 'vuex';

Vue.prototype.$csrfTokenName = window.csrfTokenName;
Vue.prototype.$csrfTokenValue = window.csrfTokenValue;
Vue.prototype.$craft = window.Craft;

new Vue({
  el: '#main',
  store: TranslationsStore,
  components: {
    SaveTranslations,
    TranslationsList,
    TranslationsToolbar,
    TranslationsMenu,
    TranslationsFooter
  },
  computed: {
    ...mapState({
      category: state => state.category,
    })
  },
  created () {
    EventBus.$on('translations-saved', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translations saved'));
    });
    EventBus.$on('translations-saved-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translations not saved'));
    });
    EventBus.$on('translation-added', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translation added'));
    });
    EventBus.$on('translation-added-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translation not added'));
    });
    EventBus.$on('translation-deleted', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translation deleted'));
    });
    EventBus.$on('translation-deleted-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translation not deleted'));
    });
  },
  mounted () {
    // Redo Jquery selectors after Vue has mounted
    window.Craft.cp.$headerContainer = window.$('#header-container');
    window.Craft.cp.$header = window.$('#header');
    window.Craft.cp.$mainContent = window.$('#main-content');
    window.Craft.cp.$details = window.$('#details');
    window.Craft.cp.$sidebarContainer = window.$('#sidebar-container');
    window.Craft.cp.$sidebar = window.$('#sidebar');
    window.Craft.cp.$contentContainer = window.$('#content-container');

    if (document.querySelector('#sidebar-toggle')) {
      document.querySelector('#sidebar-toggle').addEventListener('click', () => {
        document.body.classList.toggle('showing-sidebar');
      });
    }
  },
  watch: {
    category () {
      if (document.querySelector('#selected-sidebar-item-label')) {
        document.body.classList.remove('showing-sidebar');
        document.querySelector('#selected-sidebar-item-label').innerHTML = this.category;
      }
    }
  },
});

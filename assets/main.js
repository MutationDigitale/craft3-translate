import Vue from 'vue';
import { EventBus } from './EventBus.js';
import SaveTranslations from './components/SaveTranslations.vue';
import TranslationsList from './components/TranslationsList.vue';
import TranslationsMenu from './components/TranslationsMenu.vue';
import TranslationsFooter from './components/TranslationsFooter.vue';

Vue.prototype.$csrfTokenName = window.csrfTokenName;
Vue.prototype.$csrfTokenValue = window.csrfTokenValue;
Vue.prototype.$craft = window.Craft;

new Vue({
  el: '#main',
  components: {
    SaveTranslations,
    TranslationsList,
    TranslationsMenu,
    TranslationsFooter
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

    EventBus.$on('initial-category', (cat) => {
      document.querySelector('#selected-sidebar-item-label').innerHTML = cat;
    });

    EventBus.$on('category-changed', (cat) => {
      document.body.classList.remove('showing-sidebar');
      document.querySelector('#selected-sidebar-item-label').innerHTML = cat;
    });
  },
  mounted () {
    if (document.querySelector('#sidebar-toggle')) {
      document.querySelector('#sidebar-toggle').addEventListener('click', () => {
        document.body.classList.toggle('showing-sidebar');
      });
    }
  }
});

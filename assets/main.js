import {createApp} from 'vue';
import {store} from './store/TranslationsStore';
import SaveTranslations from './components/SaveTranslations.vue';
import TranslationsList from './components/TranslationsList.vue';
import TranslationsToolbar from './components/TranslationsToolbar.vue';
import TranslationsMenu from './components/TranslationsMenu.vue';
import TranslationsFooter from './components/TranslationsFooter.vue';
import {mapState} from 'vuex';
import mitt from 'mitt';

const emitter = mitt();

const app = createApp({
  computed: {
    ...mapState({
      category: state => state.category,
    })
  },
  created() {
    this.emitter.on('translations-saved', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translations saved'));
    });
    this.emitter.on('translations-saved-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translations not saved'));
    });
    this.emitter.on('translation-added', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translation added'));
    });
    this.emitter.on('translation-added-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translation not added'));
    });
    this.emitter.on('translation-deleted', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translation deleted'));
    });
    this.emitter.on('translation-deleted-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translation not deleted'));
    });
    this.emitter.on('translations-copied', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translations copied'));
    });
    this.emitter.on('translations-pasted', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translations pasted'));
    });
    this.emitter.on('translations-pasted-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translations not pasted'));
    });
  },
  mounted() {
    // Redo Jquery selectors after Vue has mounted
    this.$craft.cp.$headerContainer = this.$jquery('#header-container');
    this.$craft.cp.$header = this.$jquery('#header');
    this.$craft.cp.$mainContent = this.$jquery('#main-content');
    this.$craft.cp.$details = this.$jquery('#details');
    this.$craft.cp.$sidebarContainer = this.$jquery('#sidebar-container');
    this.$craft.cp.$sidebar = this.$jquery('#sidebar');
    this.$craft.cp.$contentContainer = this.$jquery('#content-container');

    if (document.querySelector('#sidebar-toggle')) {
      document.querySelector('#sidebar-toggle').addEventListener('click', () => {
        document.body.classList.toggle('showing-sidebar');
      });
    }
  },
  watch: {
    category() {
      if (document.querySelector('#selected-sidebar-item-label')) {
        document.body.classList.remove('showing-sidebar');
        document.querySelector('#selected-sidebar-item-label').innerHTML = this.category;
      }
    }
  },
});

app
  .component('SaveTranslations', SaveTranslations)
  .component('TranslationsList', TranslationsList)
  .component('TranslationsToolbar', TranslationsToolbar)
  .component('TranslationsMenu', TranslationsMenu)
  .component('TranslationsFooter', TranslationsFooter);

app.use(store);

app.config.globalProperties.$csrfTokenName = window.csrfTokenName;
app.config.globalProperties.$csrfTokenValue = window.csrfTokenValue;
app.config.globalProperties.$jquery = window.$;
app.config.globalProperties.$craft = window.Craft;
app.config.globalProperties.emitter = emitter;

app.mount('#main');

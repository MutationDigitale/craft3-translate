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
      modifiedMessagesKeys: state => state.modifiedMessagesKeys,
    })
  },
  created() {
    // Warn before leaving the page with unsaved changes
    window.addEventListener('beforeunload', (event) => {
      if (this.modifiedMessagesKeys.length > 0) {
        event.preventDefault();
        event.returnValue = '';
      }
    });

    this.emitter.on('translations-saved', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translations saved'));
    });
    this.emitter.on('translations-saved-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translations not saved'));
    });
    this.emitter.on('translation-added', () => {
      this.$craft.cp.displayNotice(this.$craft.t('translations-admin', 'Translation added'));
    });
    this.emitter.on('translation-added-error', (error) => {
      this.$craft.cp.displayError(error || this.$craft.t('translations-admin', 'Translation not added'));
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
    this.emitter.on('translations-pasted', (skipped) => {
      this.$craft.cp.displayNotice(skipped > 0
        ? this.$craft.t('translations-admin', 'Translations pasted, except {count, plural, =1{one message} other{# messages}} already in the category.', { count: skipped })
        : this.$craft.t('translations-admin', 'Translations pasted'));
    });
    this.emitter.on('translations-pasted-error', (error) => {
      this.$craft.cp.displayError(error || this.$craft.t('translations-admin', 'Translations not pasted'));
    });
    this.emitter.on('translations-exported-error', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Translations could not be exported'));
    });
    this.emitter.on('messages-deleted-meanwhile', () => {
      this.$craft.cp.displayError(this.$craft.t('translations-admin', 'Some messages were deleted in the meantime, their translations were not saved.'));
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

    // The sidebar toggle of small screens was replaced by Vue, without the listener of Craft
    this.$craft.cp.$sidebarToggle = this.$jquery('#sidebar-toggle');
    this.$craft.cp.$sidebarToggle.on('click', () => {
      this.toggleSidebar();
    });
  },
  watch: {
    category() {
      // Once a category is chosen in the sidebar of small screens
      if (document.body.classList.contains('showing-sidebar')) {
        this.toggleSidebar();
      }
    }
  },
  methods: {
    toggleSidebar() {
      if (typeof this.$craft.cp.toggleSidebar === 'function') {
        // Also updates the state of the toggle
        this.$craft.cp.toggleSidebar();
      } else {
        document.body.classList.toggle('showing-sidebar');
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

import {createApp} from 'vue';
import {store} from './store/TranslationsStore';
import SaveTranslations from './components/SaveTranslations.vue';
import TranslationsList from './components/TranslationsList.vue';
import TranslationsToolbar from './components/TranslationsToolbar.vue';
import TranslationsMenu from './components/TranslationsMenu.vue';
import TranslationsFooter from './components/TranslationsFooter.vue';
import {mapGetters, mapState} from 'vuex';
import mitt from 'mitt';

const emitter = mitt();

const app = createApp({
  computed: {
    ...mapState({
      category: state => state.category,
    }),
    ...mapGetters({
      isModified: 'isModified',
    })
  },
  created() {
    // Warn before leaving the page with unsaved changes
    window.addEventListener('beforeunload', (event) => {
      if (this.isModified) {
        event.preventDefault();
        event.returnValue = '';
      }
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

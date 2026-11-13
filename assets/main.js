import {createApp, onMounted, watch} from 'vue';
import {store} from './store/TranslationsStore';
import SaveTranslations from './components/SaveTranslations.vue';
import TranslationsList from './components/TranslationsList.vue';
import TranslationsToolbar from './components/TranslationsToolbar.vue';
import TranslationsMenu from './components/TranslationsMenu.vue';
import TranslationsFooter from './components/TranslationsFooter.vue';
import mitt from 'mitt';
import { useCraft } from './helpers';

const emitter = mitt();

const app = createApp({
  setup() {
    const { craft, jquery } = useCraft();

    function toggleSidebar() {
      if (typeof craft.cp.toggleSidebar === 'function') {
        // Also updates the state of the toggle
        craft.cp.toggleSidebar();
      } else {
        document.body.classList.toggle('showing-sidebar');
      }
    }

    // Warn before leaving the page with unsaved changes
    window.addEventListener('beforeunload', (event) => {
      if (store.getters.isModified) {
        event.preventDefault();
        event.returnValue = '';
      }
    });

    // Once a category is chosen in the sidebar of small screens
    watch(() => store.state.category, () => {
      if (document.body.classList.contains('showing-sidebar')) {
        toggleSidebar();
      }
    });

    onMounted(() => {
      // Redo Jquery selectors after Vue has mounted
      craft.cp.$headerContainer = jquery('#header-container');
      craft.cp.$header = jquery('#header');
      craft.cp.$mainContent = jquery('#main-content');
      craft.cp.$details = jquery('#details');
      craft.cp.$sidebarContainer = jquery('#sidebar-container');
      craft.cp.$sidebar = jquery('#sidebar');
      craft.cp.$contentContainer = jquery('#content-container');

      // The sidebar toggle of small screens was replaced by Vue, without the listener of Craft
      craft.cp.$sidebarToggle = jquery('#sidebar-toggle');
      craft.cp.$sidebarToggle.on('click', () => {
        toggleSidebar();
      });
    });
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

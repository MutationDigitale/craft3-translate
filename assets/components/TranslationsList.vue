<template>
    <div>
        <div class="translate-columns">
            <div v-for="language in languages" v-bind:key="language.id">
                <h2>{{ language.displayName }}</h2>
                <div v-for="(sourceMessage, key) in sourceMessages" v-bind:key="sourceMessage.id" class="field"
                     :class="{'modified': isModified(sourceMessage, key, language)}">
                    <div class="heading">
                        <label :for="sourceMessage.id">
                            {{ sourceMessage.message }}
                        </label>
                    </div>
                    <div class="input ltr">
                        <input class="text nicetext fullwidth" type="text"
                               :id="sourceMessage.id"
                               v-model="sourceMessage.languages[language.id]"
                               @change="change()"
                               @keyup="change()"
                               data-show-chars-left="" autocomplete="off" placeholder="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { EventBus } from '../EventBus';

export default {
  data () {
    return {
      isLoading: false,
      isSaving: false,
      languages: [],
      originalSourceMessages: [],
      sourceMessages: [],
    };
  },
  mounted () {
    this.getTranslations();

    EventBus.$on('translations-saved', () => {
      this.originalSourceMessages = this.copyObj(this.sourceMessages);
    });
  },
  methods: {
    getTranslations: function () {
      this.isLoading = true;

      axios
        .get('/actions/translate/translate/get-translations')
        .then((response) => {
          this.languages = response.data.languages;
          this.sourceMessages = response.data.sourceMessages;
          this.originalSourceMessages = this.copyObj(this.sourceMessages);
        })
        .catch((error) => {
          console.log(error);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    change: function () {
      let sourceMessages = null;

      this.sourceMessages.forEach((sourceMessage, key) => {
        this.languages.forEach((language) => {
          if (this.isModified(sourceMessage, key, language)) {
            if (sourceMessages === null) {
              sourceMessages = [];
            }
            if (!(language.id in sourceMessages)) {
              sourceMessages[language.id] = [];
            }
            sourceMessages[language.id][sourceMessage.id] = sourceMessage.languages[language.id]
              ? sourceMessage.languages[language.id]
              : '';
          }
        });
      });

      EventBus.$emit('translations-modified', sourceMessages);
    },
    isModified: function (sourceMessage, key, language) {
      const originalValue = this.originalSourceMessages[key].languages[language.id];
      const newValue = sourceMessage.languages[language.id];
      if ((originalValue === '' || originalValue === null) &&
        newValue === '' || newValue === null) {
        return false;
      }
      return originalValue !== newValue;
    },
    copyObj: function(obj) {
      return JSON.parse(JSON.stringify(obj));
    }
  }
};
</script>

<style scoped>
.modified label::after {
    content: ' *';
}

.modified .text {
    border-color: black;
}

.translate-columns {
    display: flex;
    margin: 0 -12px;
}

.translate-columns > * {
    flex-grow: 1;
    flex-basis: 0;
    margin: 0 12px;
}
</style>

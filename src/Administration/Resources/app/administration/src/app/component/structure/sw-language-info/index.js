import template from './sw-language-info.html.twig';
import './sw-language-info.scss';

const { Component, StateDeprecated } = Shopware;

/**
 * @public
 * @description
 * Renders information text about the current language
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-language-info
 *  :entityDescription="Produkt 1">
 * </sw-language-info>
 */
Component.register('sw-language-info', {
    template,

    props: {
        entityDescription: {
            type: String,
            required: false,
            default: ''
        },
        isNewEntity: {
            type: Boolean,
            required: false,
            default: false
        },
        changeLanguageOnParentClick: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            parentLanguage: { name: '' },
            language: {},
            infoParent: ''
        };
    },

    computed: {
        languageStore() {
            return StateDeprecated.getStore('language');
        },
        infoText() {
            const language = this.language;

            // Actual language is system default, because we are creating a new entity
            if (this.isNewEntity) {
                return this.$tc(
                    'sw-language-info.infoTextNewEntity',
                    0,
                    { entityDescription: this.entityDescription }
                );
            }

            // Actual language is a child language with the root language as fallback
            if (language.parentId !== null && language.parentId.length > 0) {
                return this.$tc(
                    'sw-language-info.infoTextChildLanguage',
                    0,
                    {
                        entityDescription: this.entityDescription,
                        language: language.name
                    }
                );
            }

            // Actual language is the system default language
            if (this.isSystemDefaultLanguage(language.id)) {
                return '';
            }

            // Actual language is a root language with the system default language as fallback
            return this.$tc(
                'sw-language-info.infoTextRootLanguage',
                0,
                {
                    entityDescription: this.entityDescription,
                    language: language.name
                }
            );
        }
    },

    watch: {
        // Watch the id because of ajax loading
        'language.name': {
            handler() {
                this.refreshParentLanguage();
            }
        },
        'parentLanguage.name': {
            handler() {
                this.infoParent = this.parentLanguage.name;
            }
        }
    },

    destroyed() {
        this.destroyedComponent();
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.language = this.languageStore.getCurrentLanguage();

            // Refresh the info when the application language is changed
            this.$root.$on('on-change-application-language', this.refreshLanguage);
        },

        destroyedComponent() {
            this.$root.$off('on-change-application-language', this.refreshLanguage);
        },

        isSystemDefaultLanguage(languageId) {
            return languageId === this.languageStore.systemLanguageId;
        },

        refreshLanguage() {
            this.language = this.languageStore.getCurrentLanguage();
        },

        refreshParentLanguage() {
            if (this.language.id.length < 1 || this.language.id === this.languageStore.systemLanguageId) {
                this.parentLanguage = { name: '' };
                return;
            }

            if (this.language.parentId !== null && this.language.parentId.length > 0) {
                this.parentLanguage = this.languageStore.getById(this.language.parentId);
                return;
            }

            this.parentLanguage = this.languageStore.getById(this.languageStore.systemLanguageId);
        },

        onClickParentLanguage() {
            if (!this.changeLanguageOnParentClick) {
                return;
            }
            this.$root.$emit('on-change-language-clicked', this.parentLanguage.id);
        }
    }
});

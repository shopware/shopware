import template from './sw-language-info.html.twig';
import './sw-language-info.scss';
import { EventBus, getApiContext, repositoryFactoryService, useContextStore, warn } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 * @description
 * Renders information text about the current language
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-language-info
 *     :entityDescription="Produkt 1">
 * </sw-language-info>
 */
export default {
    template,

    inject: [repositoryFactoryService],

    props: {
        entityDescription: {
            type: String,
            required: false,
            default: '',
        },
        isNewEntity: {
            type: Boolean,
            required: false,
            default: false,
        },
        changeLanguageOnParentClick: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            parentLanguage: { name: '' },
        };
    },

    computed: {
        languageId() {
            return useContextStore().api.languageId;
        },

        systemLanguageId() {
            return useContextStore().api.systemLanguageId;
        },

        language() {
            return useContextStore().api.language;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        infoParent() {
            return this.parentLanguage.name;
        },

        infoText() {
            // Actual language is system default, because we are creating a new entity
            if (this.isNewEntity) {
                return this.$t(
                    'sw-language-info.infoTextNewEntity',
                    {
                        entityDescription: this.entityDescription,
                    },
                    0,
                );
            }

            if (this.language === null) {
                return '';
            }

            // Actual language is a child language with the root language as fallback
            if (this.language.parentId !== null && this.language.parentId.length > 0) {
                return this.$t(
                    'sw-language-info.infoTextChildLanguage',
                    {
                        entityDescription: this.entityDescription,
                        language: this.language.name,
                    },
                    0,
                );
            }

            // Actual language is the system default language
            if (this.isDefaultLanguage) {
                return '';
            }

            // Actual language is a root language with the system default language as fallback
            return this.$t(
                'sw-language-info.infoTextRootLanguage',
                {
                    entityDescription: this.entityDescription,
                    language: this.language.name,
                },
                0,
            );
        },

        isDefaultLanguage() {
            return this.languageId === this.systemLanguageId;
        },
    },

    watch: {
        // Watch the id because of ajax loading
        'language.name': {
            handler() {
                this.refreshParentLanguage().catch((error) => warn(error));
            },
        },
    },

    methods: {
        async refreshParentLanguage() {
            if (this.language.id.length < 1 || this.isDefaultLanguage) {
                this.parentLanguage = { name: '' };
                return;
            }

            if (this.language.parentId !== null && this.language.parentId.length > 0) {
                this.parentLanguage = await this.languageRepository.get(this.language.parentId, getApiContext());
                return;
            }

            this.parentLanguage = await this.languageRepository.get(this.systemLanguageId, getApiContext());
        },

        onClickParentLanguage() {
            if (!this.changeLanguageOnParentClick) {
                return;
            }

            EventBus.emit('on-change-language-clicked', this.parentLanguage.id);
        },
    },
};

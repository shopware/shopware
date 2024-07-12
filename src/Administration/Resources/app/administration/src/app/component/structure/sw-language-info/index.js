import template from './sw-language-info.html.twig';
import './sw-language-info.scss';

const { Component } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();
const { warn } = Shopware.Utils.debug;

/**
 * @package admin
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
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-language-info', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

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
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            parentLanguage: { name: '' },
        };
    },

    computed: {
        ...mapState('context', {
            languageId: state => state.api.languageId,
            systemLanguageId: state => state.api.systemLanguageId,
            language: state => state.api.language,
        }),

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        infoParent() {
            return this.parentLanguage.name;
        },

        infoText() {
            // Actual language is system default, because we are creating a new entity
            if (this.isNewEntity) {
                return this.$tc(
                    'sw-language-info.infoTextNewEntity',
                    0,
                    { entityDescription: this.entityDescription },
                );
            }

            if (this.language === null) {
                return '';
            }

            // Actual language is a child language with the root language as fallback
            if (this.language.parentId !== null && this.language.parentId.length > 0) {
                return this.$tc(
                    'sw-language-info.infoTextChildLanguage',
                    0,
                    {
                        entityDescription: this.entityDescription,
                        language: this.language.name,
                    },
                );
            }

            // Actual language is the system default language
            if (this.isDefaultLanguage) {
                return '';
            }

            // Actual language is a root language with the system default language as fallback
            return this.$tc(
                'sw-language-info.infoTextRootLanguage',
                0,
                {
                    entityDescription: this.entityDescription,
                    language: this.language.name,
                },
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
                this.refreshParentLanguage().catch(error => warn(error));
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
                this.parentLanguage = await this.languageRepository.get(this.language.parentId, Shopware.Context.api);
                return;
            }

            this.parentLanguage = await this.languageRepository.get(this.systemLanguageId, Shopware.Context.api);
        },

        onClickParentLanguage() {
            if (!this.changeLanguageOnParentClick) {
                return;
            }

            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$root.$emit('on-change-language-clicked', this.parentLanguage.id);
            } else {
                Shopware.Utils.EventBus.emit('on-change-language-clicked', this.parentLanguage.id);
            }
        },
    },
});

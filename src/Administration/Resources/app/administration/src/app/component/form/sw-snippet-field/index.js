import template from './sw-snippet-field.html.twig';
import './sw-snippet-field.scss';

const { Component, State, Data: { Criteria } } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Input field that allows you to easily edit and translate snippet in a modal.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-snippet-field snippet="myPlugin.test.snippet" fieldType="text"></sw-switch-field>
 */
Component.register('sw-snippet-field', {
    template,

    inject: [
        'snippetSetService',
        'repositoryFactory',
    ],

    props: {
        snippet: {
            type: String,
            required: true,
        },

        fieldType: {
            type: String,
            required: false,
            default: 'text',
            validValues: ['text', 'textarea'],
            validator(value) {
                return ['text', 'textarea'].includes(value);
            },
        },
    },

    data() {
        return {
            textValue: this.snippet,
            snippets: [],
            snippetSets: [],
            showEditModal: false,
            isLoading: false,
        };
    },

    computed: {
        snippetSetRepository() {
            return this.repositoryFactory.create('snippet_set');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('id', Shopware.Context.api.systemLanguageId));
            criteria.addAssociation('locale');

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            const translations = await this.snippetSetService.getCustomList(1, 25, { translationKey: [this.snippet] });

            if (translations.total < 1) {
                this.snippets = [];
            } else {
                this.snippets = translations.data[this.snippet];
            }

            this.snippetSets = await this.snippetSetRepository.search(new Criteria(1, 25), Shopware.Context.api);

            await this.updatePlaceholderValueToSnippetTranslation();

            this.isLoading = false;
        },

        async updatePlaceholderValueToSnippetTranslation() {
            if (this.snippets.length < 1) {
                return;
            }

            const currentLocale = State.get('session').currentLocale;
            let translation = this.getTranslationByLocale(currentLocale);
            if (translation) {
                this.textValue = translation.value;
                return;
            }

            const systemDefaultLocale = await this.getSystemDefaultLocale();
            translation = this.getTranslationByLocale(systemDefaultLocale);
            if (translation) {
                this.textValue = translation.value;
                return;
            }

            translation = this.getTranslationByLocale('en-GB');
            if (translation) {
                this.textValue = translation.value;
            }
        },

        getTranslationByLocale(locale) {
            const snippetSet = this.snippetSets.find((set) => {
                return set.iso === locale;
            });

            if (!snippetSet) {
                return null;
            }

            return this.snippets.find((translation) => {
                return translation.setId === snippetSet.id && translation.value !== '';
            });
        },

        async getSystemDefaultLocale() {
            const languages = await this.languageRepository.search(this.languageCriteria, Shopware.Context.api);

            return languages.first().locale.code;
        },

        openEditModal() {
            this.showEditModal = true;
        },

        closeEditModal() {
            this.showEditModal = false;
        },

        onSave() {
            this.createdComponent();
            this.closeEditModal();
        },
    },
});

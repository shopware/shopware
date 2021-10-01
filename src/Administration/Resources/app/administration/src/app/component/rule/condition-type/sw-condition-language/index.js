import template from './sw-condition-language.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LanguageRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-language :condition="condition" :level="0"></sw-condition-language>
 */
Component.extend('sw-condition-language', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory'],

    data() {
        return {
            languages: null,
            inputKey: 'languageIds',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.languageIds || [];
            },
            set(languageIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, languageIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.languageIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueLanguageIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.languages = new EntityCollection(
                this.languageRepository.route,
                this.languageRepository.entityName,
                Context.api,
            );

            if (this.languageIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.languageIds);

            return this.languageRepository.search(criteria, Context.api).then((languages) => {
                this.languages = languages;
            });
        },

        setLanguageIds(languages) {
            this.languageIds = languages.getIds();
            this.languages = languages;
        },
    },
});

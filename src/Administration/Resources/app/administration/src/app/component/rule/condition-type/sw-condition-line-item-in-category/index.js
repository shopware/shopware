import template from './sw-condition-line-item-in-category.html.twig';
import './sw-condition-line-item-in-category.scss';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @package business-ops
 */
Component.extend('sw-condition-line-item-in-category', 'sw-condition-base-line-item', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory', 'feature'],

    data() {
        return {
            categories: null,
            inputKey: 'categoryIds',
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        categoryIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.categoryIds || [];
            },
            set(categoryIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, categoryIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.categoryIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueCategoryIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.categories = new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Context.api,
            );

            if (this.categoryIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria(1, 25);
            criteria.setIds(this.categoryIds);

            return this.categoryRepository.search(criteria, Context.api).then((categories) => {
                this.categories = categories;
            });
        },

        setCategoryIds(categories) {
            this.categoryIds = categories.getIds();
            this.categories = categories;
        },

        getCategoryBreadcrumb(category) {
            if (!category.breadcrumb || Object.keys(category.breadcrumb).length === 0) {
                return category.name;
            }

            return Object.values(category.breadcrumb).join(' / ');
        },
    },
});

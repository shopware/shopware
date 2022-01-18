import template from './sw-condition-promotion-line-item.html.twig';
import './sw-condition-line-item.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the PromotionLineItemRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-promotion-line-item :condition="condition" :level="0"></sw-condition-line-item>
 */
Component.extend('sw-condition-promotion-line-item', 'sw-condition-base-line-item', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            promotions: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        promotionRepository() {
            return this.repositoryFactory.create('promotion');
        },

        promotionIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.identifiers || [];
            },
            set(identifiers) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, identifiers };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.identifiers']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueIdentifiersError;
        },

        promotionCriteria() {
            return new Criteria();
        },

        resultCriteria() {
            return new Criteria();
        },

        promotionContext() {
            return Shopware.Context.api;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.promotions = new EntityCollection(
                this.promotionRepository.route,
                this.promotionRepository.entityName,
                Shopware.Context.api,
            );


            if (this.promotionIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.promotionIds);

            return this.promotionRepository.search(criteria, Shopware.Context.api).then((promotions) => {
                this.promotions = promotions;
            });
        },

        setIds(promotionCollection) {
            this.promotionIds = promotionCollection.getIds();
            this.promotions = promotionCollection;
        },
    },
});

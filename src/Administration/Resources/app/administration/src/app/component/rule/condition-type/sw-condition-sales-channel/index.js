import template from './sw-condition-sales-channel.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the SalesChannelRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-sales-channel :condition="condition" :level="0"></sw-condition-sales-channel>
 */
Component.extend('sw-condition-sales-channel', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory'],

    data() {
        return {
            salesChannels: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.salesChannelIds || [];
            },
            set(salesChannelIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, salesChannelIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.salesChannelIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueSalesChannelIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannels = new EntityCollection(
                this.salesChannelRepository.route,
                this.salesChannelRepository.entityName,
                Context.api,
            );

            if (this.salesChannelIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.salesChannelIds);

            return this.salesChannelRepository.search(criteria, Context.api).then((salesChannels) => {
                this.salesChannels = salesChannels;
            });
        },

        setSalesChannelIds(salesChannels) {
            this.salesChannelIds = salesChannels.getIds();
            this.salesChannels = salesChannels;
        },
    },
});

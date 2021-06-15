import template from './sw-condition-customer-group.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the CustomerGroupRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-group :condition="condition" :level="0"></sw-condition-customer-group>
 */
Component.extend('sw-condition-customer-group', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            customerGroups: null,
        };
    },

    computed: {
        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        customerGroupIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.customerGroupIds || [];
            },
            set(customerGroupIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, customerGroupIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.customerGroupIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueCustomerGroupIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customerGroups = new EntityCollection(
                this.customerGroupRepository.route,
                this.customerGroupRepository.entityName,
                Context.api,
            );

            if (this.customerGroupIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.customerGroupIds);

            return this.customerGroupRepository.search(criteria, Context.api).then((customerGroups) => {
                this.customerGroups = customerGroups;
            });
        },

        setCustomerGroupIds(customerGroups) {
            this.customerGroupIds = customerGroups.getIds();
            this.customerGroups = customerGroups;
        },
    },
});

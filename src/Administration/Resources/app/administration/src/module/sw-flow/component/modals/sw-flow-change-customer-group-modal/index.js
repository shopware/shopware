import template from './sw-flow-change-customer-group-modal.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            customerGroupId: '',
            fieldError: null,
        };
    },

    computed: {
        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        customerGroupCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        ...mapState('swFlowState', ['customerGroups']),
    },

    watch: {
        customerGroupId(value) {
            if (value && this.fieldError) {
                this.fieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customerGroupId = this.sequence?.config?.customerGroupId || '';

            if (!this.customerGroups.length) {
                this.customerGroupRepository.search(this.customerGroupCriteria).then((data) => {
                    Shopware.State.commit('swFlowState/setCustomerGroups', data);
                });
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            if (!this.customerGroupId) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    customerGroupId: this.customerGroupId,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
};

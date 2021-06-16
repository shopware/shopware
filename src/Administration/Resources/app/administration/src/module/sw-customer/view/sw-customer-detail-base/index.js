import template from './sw-customer-detail-base.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-detail-base', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true,
        },

        customerEditMode: {
            type: Boolean,
            required: true,
            default: false,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            customerCustomFieldSets: null,
        };
    },

    computed: {
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria();

            criteria
                .addFilter(Criteria.equals('relations.entityName', 'customer'));

            criteria.getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('shopwareApps/setSelectedIds', this.customer.id ? [this.customer.id] : []);

            this.customFieldSetRepository.search(this.customFieldSetCriteria)
                .then((customFieldSets) => {
                    this.customerCustomFieldSets = customFieldSets;
                });
        },
    },
});

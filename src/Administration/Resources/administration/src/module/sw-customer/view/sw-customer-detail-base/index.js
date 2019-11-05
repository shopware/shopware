import template from './sw-customer-detail-base.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-detail-base', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true
        },

        customerEditMode: {
            type: Boolean,
            required: true,
            default: false
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            customerCustomFieldSets: null
        };
    },

    computed: {
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const customFieldSetCriteria = new Criteria();
            customFieldSetCriteria.addFilter(Criteria.equals('relations.entityName', 'customer'))
                .addAssociation('customFields');

            this.customFieldSetRepository.search(customFieldSetCriteria, Shopware.Context.api).then((customFieldSets) => {
                this.customerCustomFieldSets = customFieldSets;
            });
        }
    }
});

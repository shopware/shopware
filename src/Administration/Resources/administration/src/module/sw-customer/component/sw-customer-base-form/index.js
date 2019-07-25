import { Component } from 'src/core/shopware';
import { mapApiErrors } from 'src/app/service/map-errors.service';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-customer-base-form.html.twig';

Component.register('sw-customer-base-form', {
    template,

    inject: [
        'repositoryFactory',
        'context',
        'swCustomerCreateOnChangeSalesChannel'
    ],

    props: {
        customer: {
            type: Object,
            required: true
        },
        salesChannels: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        customerGroups: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        paymentMethods: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            salutations: null
        };
    },

    computed: {
        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        ...mapApiErrors('customer', [
            'salutationId',
            'firstName',
            'lastName',
            'email',
            'groupId',
            'salesChannelId',
            'defaultPaymentMethodId',
            'customerNumber'
        ])
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria(1, 500);
            this.salutationRepository.search(criteria, this.context).then((searchResult) => {
                this.salutations = searchResult;
            });
        }
    }
});

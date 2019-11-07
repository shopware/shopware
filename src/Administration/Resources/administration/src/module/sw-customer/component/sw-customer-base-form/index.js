import template from './sw-customer-base-form.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-base-form', {
    template,

    inject: [
        'repositoryFactory',
        'apiContext',
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
            'customerNumber',
            'password'
        ])
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria(1, 500);
            this.salutationRepository.search(criteria, this.apiContext).then((searchResult) => {
                this.salutations = searchResult;
            });
        }
    }
});

import template from './sw-customer-address-form.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-address-form', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    props: {
        customer: {
            type: Object,
            required: true
        },

        address: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        countries: {
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
        ...mapApiErrors('address', [
            'company',
            'department',
            'salutationId',
            'title',
            'firstName',
            'lastName',
            'street',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'zipcode',
            'city',
            'countryId',
            'phoneNumber',
            'vatId'
        ]),

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        ...mapApiErrors('address', ['countryId', 'salutationId', 'city', 'street', 'zipcode', 'lastName', 'firstName'])
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

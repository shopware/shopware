import template from './sw-customer-detail-base.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['customFieldDataProviderService'],

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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldDataProviderService.getCustomFieldSets('customer').then((customFieldSets) => {
                this.customerCustomFieldSets = customFieldSets;
            });
        },
    },
};

import template from './sw-customer-default-addresses.html.twig';
import './sw-customer-default-addresses.scss';

/**
 * @package customer-order
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['customSnippetApiService'],

    props: {
        customer: {
            type: Object,
            required: true,
        },

        customerEditMode: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            formattingShippingAddress: '',
            formattingBillingAddress: '',
        };
    },

    computed: {
        defaultShippingAddressLink() {
            return {
                name: 'sw.customer.detail.addresses',
                params: {
                    id: this.customer.id,
                },
                query: {
                    detailId: this.customer.defaultShippingAddress.id,
                    edit: this.customerEditMode,
                },
            };
        },

        defaultBillingAddressLink() {
            return {
                name: 'sw.customer.detail.addresses',
                params: {
                    id: this.customer.id,
                },
                query: {
                    detailId: this.customer.defaultBillingAddress.id,
                    edit: this.customerEditMode,
                },
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.renderFormattingAddress();
        },

        renderFormattingAddress() {
            this.customSnippetApiService
                .render(
                    this.customer.defaultShippingAddress,
                    this.customer.defaultShippingAddress.country?.addressFormat,
                ).then((res) => {
                    this.formattingShippingAddress = res.rendered;
                });

            this.customSnippetApiService
                .render(
                    this.customer.defaultBillingAddress,
                    this.customer.defaultBillingAddress.country?.addressFormat,
                ).then((res) => {
                    this.formattingBillingAddress = res.rendered;
                });
        },
    },
};

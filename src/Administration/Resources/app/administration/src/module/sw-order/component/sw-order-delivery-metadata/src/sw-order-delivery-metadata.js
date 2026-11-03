import template from './sw-order-delivery-metadata.html.twig';
import './sw-order-delivery-metadata.scss';
import { customSnippetApiService, getCurrencyFilter, getDateFilter } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    inject: [customSnippetApiService],

    props: {
        delivery: {
            type: Object,
            required: true,
            default: () => {},
        },
        order: {
            type: Object,
            required: true,
            default: () => {},
        },
        title: {
            type: String,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            formattingAddress: '',
        };
    },

    computed: {
        currencyFilter() {
            return getCurrencyFilter();
        },

        dateFilter() {
            return getDateFilter();
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
                .render(this.delivery.shippingOrderAddress, this.delivery.shippingOrderAddress.country.addressFormat)
                .then((res) => {
                    this.formattingAddress = res.rendered;
                });
        },
    },
};

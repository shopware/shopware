import template from './sw-order-delivery-metadata.html.twig';
import './sw-order-delivery-metadata.scss';

/**
 * @package customer-order
 */

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-delivery-metadata', {
    template,

    inject: ['customSnippetApiService'],

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
                    this.delivery.shippingOrderAddress,
                    this.delivery.shippingOrderAddress.country.addressFormat,
                ).then((res) => {
                    this.formattingAddress = res.rendered;
                });
        },
    },
});

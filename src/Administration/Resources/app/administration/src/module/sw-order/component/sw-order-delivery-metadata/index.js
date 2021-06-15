import template from './sw-order-delivery-metadata.html.twig';
import './sw-order-delivery-metadata.scss';

const { Component } = Shopware;

Component.register('sw-order-delivery-metadata', {
    template,

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
});

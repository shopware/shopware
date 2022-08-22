import template from './sw-order-customer-comment.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-customer-comment', {
    template,

    props: {
        customerComment: {
            type: String,
            required: true,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
});

import template from './sw-order-customer-comment.html.twig';

const { Component } = Shopware;

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

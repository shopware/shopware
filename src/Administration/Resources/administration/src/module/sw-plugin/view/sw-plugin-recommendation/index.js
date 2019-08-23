import template from './sw-plugin-recommendation.html.twig';

const { Component } = Shopware;

Component.register('sw-plugin-recommendation', {
    template,

    props: {
        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    }
});

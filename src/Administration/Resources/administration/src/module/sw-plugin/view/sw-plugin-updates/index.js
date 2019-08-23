import template from './sw-plugin-updates-list.html.twig';

const { Component } = Shopware;

Component.register('sw-plugin-updates', {
    template,

    props: {
        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    }
});

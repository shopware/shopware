import template from './sw-flow-list.html.twig';

const { Component } = Shopware;

Component.register('sw-flow-list', {
    template,

    inject: ['acl'],

    data() {
        return {
            total: 0,
            isLoading: false,
        };
    },
});

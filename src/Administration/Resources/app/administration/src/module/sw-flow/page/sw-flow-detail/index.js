import template from './sw-flow-detail.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-flow-detail', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            flow: {},
        };
    },
});

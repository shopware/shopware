import template from './sw-users-permissions.html.twig';

const { Component } = Shopware;

Component.register('sw-users-permissions', {
    template,

    data() {
        return {};
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {},

    methods: {}
});

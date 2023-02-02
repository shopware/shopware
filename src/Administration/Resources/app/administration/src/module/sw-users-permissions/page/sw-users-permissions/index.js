import template from './sw-users-permissions.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-users-permissions', {
    template,

    inject: ['feature'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        reloadUserListing() {
            if (this.$refs.userListing) {
                this.$refs.userListing.getList();
            }

            if (this.$refs.roleListing) {
                this.$refs.roleListing.getList();
            }
        },
    },
});

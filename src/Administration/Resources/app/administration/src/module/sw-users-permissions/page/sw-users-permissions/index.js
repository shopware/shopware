import template from './sw-users-permissions.html.twig';

const { Component } = Shopware;

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

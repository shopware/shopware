import template from './sw-settings-rule-assignment-listing.html.twig';

const { Component } = Shopware;

Component.extend('sw-settings-rule-assignment-listing', 'sw-entity-listing', {
    template,

    methods: {
        deleteItems() {
            this.$emit('delete-items', this.selection);

            this.isBulkLoading = false;
            this.showBulkDeleteModal = false;

            this.resetSelection();
        },
    },
});

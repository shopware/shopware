import template from './sw-settings-rule-assignment-listing.html.twig';

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['delete-items'],

    methods: {
        deleteItems() {
            this.$emit('delete-items', this.selection);

            this.isBulkLoading = false;
            this.showBulkDeleteModal = false;

            this.resetSelection();
        },
    },
};

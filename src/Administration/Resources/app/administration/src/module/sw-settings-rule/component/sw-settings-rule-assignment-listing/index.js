import template from './sw-settings-rule-assignment-listing.html.twig';

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    methods: {
        deleteItems() {
            this.$emit('delete-items', this.selection);

            this.isBulkLoading = false;
            this.showBulkDeleteModal = false;

            this.resetSelection();
        },
    },
};

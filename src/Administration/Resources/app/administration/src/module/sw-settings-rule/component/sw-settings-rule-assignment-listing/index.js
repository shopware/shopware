import template from './sw-settings-rule-assignment-listing.html.twig';

/**
 * @private
 * @package services-settings
 */
export default {
    template,

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

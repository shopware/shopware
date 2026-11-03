/**
 * @sw-package after-sales
 */

import template from './sw-order-detail-documents.html.twig';
import { useOrderDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    emits: ['save-and-reload', 'update-loading'],

    inject: {
        swOrderDetailOnSaveAndReload: {
            from: 'swOrderDetailOnSaveAndReload',
            default: null,
        },
        swOrderDetailOnLoadingChange: {
            from: 'swOrderDetailOnLoadingChange',
            default: null,
        },
    },

    computed: {
        isLoading: () => useOrderDetailStore().isLoading,

        order: () => useOrderDetailStore().order,

        versionContext: () => useOrderDetailStore().versionContext,

        isEditing: () => useOrderDetailStore().isEditing,

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        isV68Active() {
            return this.feature?.isActive('v6.8.0.0');
        },
    },

    methods: {
        saveAndReload() {
            if (this.swOrderDetailOnSaveAndReload) {
                this.swOrderDetailOnSaveAndReload();
            } else {
                this.$emit('save-and-reload');
            }
        },

        onUpdateLoading(loading) {
            if (this.swOrderDetailOnLoadingChange) {
                this.swOrderDetailOnLoadingChange(loading);
            } else {
                this.$emit('update-loading', loading);
            }
        },
    },
};

/**
 * @sw-package after-sales
 */

import useSwOrderDetailStore from 'shopware:stores/swOrderDetail';
import template from './sw-order-detail-documents.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'save-and-reload',
        'update-loading',
    ],

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
        isLoading: () => useSwOrderDetailStore().isLoading,

        order: () => useSwOrderDetailStore().order,

        versionContext: () => useSwOrderDetailStore().versionContext,

        isEditing: () => useSwOrderDetailStore().isEditing,

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

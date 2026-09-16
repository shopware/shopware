/**
 * @sw-package after-sales
 */

import template from './sw-order-detail-documents.html.twig';

const { Store } = Shopware;

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
        isLoading: () => Store.get('swOrderDetail').isLoading,

        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        isEditing: () => Store.get('swOrderDetail').isEditing,

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        isV68Active() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-order-detail-documents.isV68Active is deprecated. Will be removed without replacement.',
            );

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

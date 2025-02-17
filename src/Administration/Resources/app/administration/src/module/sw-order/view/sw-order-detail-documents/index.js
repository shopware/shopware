import template from './sw-order-detail-documents.html.twig';

/**
 * @sw-package checkout
 */

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
    },

    methods: {
        saveAndReload() {
            this.$emit('save-and-reload');
            if (this.swOrderDetailOnSaveAndReload) {
                this.swOrderDetailOnSaveAndReload();
            }
        },

        onUpdateLoading(loading) {
            this.$emit('update-loading', loading);
            if (this.swOrderDetailOnLoadingChange) {
                this.swOrderDetailOnLoadingChange(loading);
            }
        },
    },
};

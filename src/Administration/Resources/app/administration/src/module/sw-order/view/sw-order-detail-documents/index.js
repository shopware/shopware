import template from './sw-order-detail-documents.html.twig';

/**
 * @sw-package checkout
 */

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
        ...mapGetters('swOrderDetail', [
            'isLoading',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),
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

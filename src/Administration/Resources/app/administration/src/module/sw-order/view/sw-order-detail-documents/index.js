import template from './sw-order-detail-documents.html.twig';

/**
 * @package checkout
 */

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

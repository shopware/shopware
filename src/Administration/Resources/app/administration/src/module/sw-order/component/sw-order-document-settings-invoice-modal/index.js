import template from './sw-order-document-settings-invoice-modal.html.twig';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        addAdditionalInformationToDocument() {
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
        },

        onPreview() {
            this.$emit('loading-preview');
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super('onPreview');
        },
    },
};

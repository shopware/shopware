import template from './sw-order-document-settings-invoice-modal.html.twig';

const { Mixin } = Shopware;

/**
 * @sw-package after-sales
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['loading-preview'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    computed: {
        documentNumber: {
            get() {
                return String(this.documentConfig.documentNumber);
            },
            set(value) {
                this.documentConfig.documentNumber = value;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        addAdditionalInformationToDocument() {
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
        },

        onPreview(fileType = 'pdf') {
            this.$emit('loading-preview');
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super('onPreview', fileType);
        },
    },
};

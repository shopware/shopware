import template from './sw-order-document-settings-modal.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-order-document-settings-modal', {
    template,

    inject: ['numberRangeService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        currentDocumentType: {
            type: Object,
            required: true,
        },
        isLoadingDocument: {
            type: Boolean,
            required: true,
        },
        isLoadingPreview: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            showModal: false,
            selectedDocumentFile: false,
            uploadDocument: false,
            documentConfig: {
                custom: {},
                documentNumber: 0,
                documentComment: '',
                documentDate: '',
            },
            documentNumberPreview: false,
            features: {
                uploadFileSizeLimit: 52428800,
                fileTypes: ['application/pdf'],
            },
        };
    },

    computed: {
        documentPreconditionsFulfilled() {
            // can be overwritten in extending component
            return true;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.numberRangeService.reserve(
                `document_${this.currentDocumentType.technicalName}`,
                this.order.salesChannelId,
                true,
            ).then((response) => {
                this.documentConfig.documentNumber = response.number;
                this.documentNumberPreview = this.documentConfig.documentNumber;
                this.documentConfig.documentDate = (new Date()).toISOString();
            });
        },

        onCreateDocument(additionalAction = false) {
            this.callDocumentCreate(additionalAction);
        },

        callDocumentCreate(additionalAction, referencedDocumentId = null) {
            this.$emit(
                'document-create',
                this.documentConfig,
                additionalAction,
                referencedDocumentId,
                (this.uploadDocument ? this.selectedDocumentFile : null),
            );
        },

        onPreview() {
            this.$emit('preview-show', this.documentConfig);
        },

        onConfirm() {
            this.$emit('page-leave-confirm');
        },

        onCancel() {
            this.$emit('page-leave');
        },

    },
});

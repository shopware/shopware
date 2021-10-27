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
        async createdComponent() {
            this.documentConfig.documentNumber = await this.reserveDocumentNumber(true);
            this.documentNumberPreview = this.documentConfig.documentNumber;
            this.documentConfig.documentDate = (new Date()).toISOString();
        },

        async onCreateDocument(additionalAction = false) {
            this.$emit('loading-document');

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                const documentNumber = await this.reserveDocumentNumber(false);

                if (documentNumber !== this.documentConfig.documentNumber) {
                    this.createNotificationInfo({
                        message: this.$tc('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                    });
                }

                this.documentConfig.documentNumber = documentNumber;
            }

            await this.addAdditionalInformationToDocument();
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

        async reserveDocumentNumber(isPreview) {
            const { number } = await this.numberRangeService.reserve(
                `document_${this.currentDocumentType.technicalName}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        addAdditionalInformationToDocument() {
            // override in specific document-settings-modals to add additional data to your document
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

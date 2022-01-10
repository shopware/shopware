import template from './sw-bulk-edit-save-modal-success.html.twig';
import './sw-bulk-edit-save-modal-success.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-success', {
    template,

    inject: ['orderDocumentApiService'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    props: {
        isDownloadingOrderDocument: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        downloadOrderDocuments() {
            return Shopware.State.get('swBulkEdit')?.orderDocuments?.download;
        },

        canDownloadOrderDocuments() {
            if (!this.downloadOrderDocuments) {
                return false;
            }

            if (!this.downloadOrderDocuments.isChanged) {
                return false;
            }

            if (!this.downloadOrderDocuments.value.length) {
                return false;
            }

            return this.downloadOrderDocuments.value.some((documentType) => {
                return documentType.selected;
            });
        },

        documentTypes() {
            return this.downloadOrderDocuments.value
                .filter((documentType) => {
                    return documentType.selected;
                })
                .map((documentType) => {
                    return documentType.technicalName;
                });
        },

        downloadDocumentPayload() {
            const payload = {};

            this.documentTypes.forEach((documentType) => {
                payload[documentType] = this.selectedIds;
            });

            return payload;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
        },

        setTitle() {
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.success.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'close',
                    label: this.$tc('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onDownloadOrderDocuments() {
            this.$emit('order-document-download', true);

            return this.executeDownloadOrderDocuments()
                .then((response) => {
                    this.$emit('order-document-download', false);

                    if (response.status === 204) {
                        this.createNotificationInfo({
                            message: this.$tc('sw-bulk-edit.modal.success.messageNoDocumentAvailable'),
                        });
                        return;
                    }

                    if (response.status === 200 && response.data) {
                        this.downloadFiles(response);
                    }
                })
                .catch((error) => {
                    this.$emit('order-document-download', false);

                    this.createNotificationError({
                        message: error.message,
                    });
                });
        },

        executeDownloadOrderDocuments() {
            return this.orderDocumentApiService.download(this.downloadDocumentPayload);
        },

        downloadFiles(response) {
            const filename = response.headers['content-disposition'].split('filename=')[1];
            const link = document.createElement('a');
            link.href = URL.createObjectURL(response.data);
            link.download = filename;
            link.dispatchEvent(new MouseEvent('click'));
            link.remove();
        },
    },
});

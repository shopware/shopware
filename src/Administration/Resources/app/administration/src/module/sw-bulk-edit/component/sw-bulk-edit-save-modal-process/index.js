import template from './sw-bulk-edit-save-modal-process.html.twig';
import './sw-bulk-edit-save-modal-process.scss';

const { Component } = Shopware;
const { chunk: chunkArray } = Shopware.Utils.array;

Component.register('sw-bulk-edit-save-modal-process', {
    template,

    inject: ['orderDocumentApiService'],

    data() {
        return {
            requestsPerPayload: 5,
            document: {
                invoice: {
                    isReached: 0,
                },
                storno: {
                    isReached: 0,
                },
                delivery_note: {
                    isReached: 0,
                },
                credit_note: {
                    isReached: 0,
                },
            },
        };
    },

    computed: {
        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        documentTypes() {
            return Shopware.State.get('swBulkEdit')?.orderDocuments?.download?.value;
        },

        documentTypeConfigs() {
            return Shopware.State.getters['swBulkEdit/documentTypeConfigs'];
        },

        selectedDocumentTypes() {
            if (this.documentTypeConfigs.length <= 0) {
                return [];
            }

            const selectedDocumentTypes = [];

            this.documentTypeConfigs.forEach((documentTypeConfig) => {
                const selectedDocumentType = this.documentTypes.find((documentType) => {
                    return documentTypeConfig.type === documentType.technicalName;
                });

                if (selectedDocumentType) {
                    selectedDocumentTypes.push(selectedDocumentType);
                }
            });

            return selectedDocumentTypes;
        },

        createDocumentPayload() {
            const payload = [];

            this.selectedIds.forEach((selectedId) => {
                this.documentTypeConfigs?.forEach((documentTypeConfig) => {
                    if (documentTypeConfig) {
                        payload.push({
                            ...documentTypeConfig,
                            orderId: selectedId,
                        });
                    }
                });
            });

            return payload;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            this.setTitle();
            await this.createDocuments();
            this.$emit('changes-apply');
        },

        setTitle() {
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.process.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'cancel',
                    label: this.$tc('sw-bulk-edit.modal.process.buttons.cancel'),
                    position: 'left',
                    action: '',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: true,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        async createDocuments() {
            if (!this.createDocumentPayload.length) {
                return;
            }

            const invoiceDocuments = this.createDocumentPayload.filter((item) => item.type === 'invoice');
            const stornoDocuments = this.createDocumentPayload.filter((item) => item.type === 'storno');
            const creditNoteDocuments = this.createDocumentPayload.filter((item) => item.type === 'credit_note');
            const deliveryNoteDocuments = this.createDocumentPayload.filter((item) => item.type === 'delivery_note');

            if (invoiceDocuments.length > 0) {
                await this.createDocument('invoice', invoiceDocuments);
            }

            if (stornoDocuments.length > 0) {
                await this.createDocument('storno', stornoDocuments);
            }

            if (creditNoteDocuments.length > 0) {
                await this.createDocument('credit_note', creditNoteDocuments);
            }

            if (deliveryNoteDocuments.length > 0) {
                await this.createDocument('delivery_note', deliveryNoteDocuments);
            }
        },

        async createDocument(documentType, payload) {
            if (payload.length <= 5) {
                await this.orderDocumentApiService.create(documentType, payload);
                this.$set(this.document[documentType], 'isReached', 100);

                return;
            }

            const chunkedPayload = chunkArray(payload, this.requestsPerPayload);
            const chunkedSize = chunkedPayload.length;
            const percentages = Math.round(100 / chunkedSize);

            // eslint-disable-next-line no-plusplus
            for (let i = 0; i < chunkedSize; i++) {
                // eslint-disable-next-line no-await-in-loop
                await this.orderDocumentApiService.create(documentType, chunkedPayload[i]);

                if (i === chunkedSize - 1) {
                    this.$set(this.document[documentType], 'isReached', 100);
                    break;
                }

                this.$set(
                    this.document[documentType],
                    'isReached',
                    this.document[documentType].isReached + percentages,
                );
            }
        },
    },
});

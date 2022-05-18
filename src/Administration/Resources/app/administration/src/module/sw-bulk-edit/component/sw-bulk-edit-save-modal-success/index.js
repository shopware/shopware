import template from './sw-bulk-edit-save-modal-success.html.twig';
import './sw-bulk-edit-save-modal-success.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { chunk: chunkArray } = Shopware.Utils.array;

Component.register('sw-bulk-edit-save-modal-success', {
    template,

    inject: ['repositoryFactory', 'orderDocumentApiService'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            latestDocuments: {},
            document: {
                invoice: {
                    isCreating: false,
                    isReached: 0,
                    isDownloading: false,
                },
                storno: {
                    isCreating: false,
                    isReached: 0,
                    isDownloading: false,
                },
                delivery_note: {
                    isCreating: false,
                    isReached: 0,
                    isDownloading: false,
                },
                credit_note: {
                    isCreating: false,
                    isReached: 0,
                    isDownloading: false,
                },
            },
        };
    },

    computed: {
        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        latestDocumentsCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equalsAny('documentTypeId', this.selectedDocumentTypes.map(item => item.id)));
            criteria.addFilter(Criteria.equalsAny('orderId', this.selectedIds));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        downloadOrderDocuments() {
            return Shopware.State.get('swBulkEdit')?.orderDocuments?.download;
        },

        selectedDocumentTypes() {
            if (!this.downloadOrderDocuments) {
                return [];
            }

            if (!this.downloadOrderDocuments.isChanged) {
                return [];
            }

            if (!this.downloadOrderDocuments.value.length) {
                return [];
            }

            return this.downloadOrderDocuments.value.filter((item) => item.selected);
        },

        documentTypeConfigs() {
            return Shopware.State.getters['swBulkEdit/documentTypeConfigs'];
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

        description() {
            return this.selectedDocumentTypes.length > 0
                ? this.$tc('sw-bulk-edit.modal.success.instruction')
                : this.$tc('sw-bulk-edit.modal.success.description');
        },

        requestsPerPayload() {
            return 10;
        },
    },

    watch: {
        document: {
            deep: true,
            handler(newValue) {
                Object.entries(newValue).forEach(([key, value]) => {
                    if (value.isReached === 100) {
                        this.$set(this.document[key], 'isCreating', false);
                    }
                });
            },
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
            await this.getLatestDocuments();
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

        async createDocuments() {
            if (!this.createDocumentPayload.length) {
                this.$set(this.document.invoice, 'isReached', 100);
                this.$set(this.document.storno, 'isReached', 100);
                this.$set(this.document.delivery_note, 'isReached', 100);
                this.$set(this.document.credit_note, 'isReached', 100);

                return;
            }

            const invoiceDocuments = this.createDocumentPayload.filter((item) => item.type === 'invoice');
            const stornoDocuments = this.createDocumentPayload.filter((item) => item.type === 'storno');
            const creditNoteDocuments = this.createDocumentPayload.filter((item) => item.type === 'credit_note');
            const deliveryNoteDocuments = this.createDocumentPayload.filter((item) => item.type === 'delivery_note');

            if (invoiceDocuments.length > 0) {
                await this.createDocument('invoice', invoiceDocuments);
            } else {
                this.$set(this.document.invoice, 'isReached', 100);
            }

            if (stornoDocuments.length > 0) {
                await this.createDocument('storno', stornoDocuments);
            } else {
                this.$set(this.document.storno, 'isReached', 100);
            }

            if (creditNoteDocuments.length > 0) {
                await this.createDocument('credit_note', creditNoteDocuments);
            } else {
                this.$set(this.document.credit_note, 'isReached', 100);
            }

            if (deliveryNoteDocuments.length > 0) {
                await this.createDocument('delivery_note', deliveryNoteDocuments);
            } else {
                this.$set(this.document.delivery_note, 'isReached', 100);
            }
        },

        async createDocument(documentType, payload) {
            this.$set(this.document[documentType], 'isCreating', true);

            if (payload.length <= 10) {
                await this.orderDocumentApiService.create(documentType, payload).then(() => {
                    this.$set(this.document[documentType], 'isReached', 100);
                });

                return;
            }

            const chunkedPayload = chunkArray(payload, this.requestsPerPayload);
            const percentages = 100 / chunkedPayload.length;

            chunkedPayload.forEach(async (item) => {
                await this.orderDocumentApiService.create(documentType, item).then(() => {
                    this.$set(this.document[documentType], 'isReached', this.document[documentType].isReached + percentages);
                });
            });
        },

        async getLatestDocuments() {
            if (this.selectedDocumentTypes.length === 0) {
                return;
            }

            const latestDocuments = {};
            const maxDocsPerType = this.selectedIds.length;

            const documents = await this.documentRepository.search(this.latestDocumentsCriteria);

            this.selectedDocumentTypes.forEach(documentType => {
                latestDocuments[documentType.technicalName] ??= [];
                const latestDoc = latestDocuments[documentType.technicalName];

                const documentsGrouped = documents.filter(document => {
                    return document.documentTypeId === documentType.id;
                });

                const latestDocKeyedByOrderId = {};

                documentsGrouped.forEach(doc => {
                    if (Object.values(latestDoc).length === maxDocsPerType) {
                        return;
                    }

                    if (!latestDocKeyedByOrderId.hasOwnProperty(doc.orderId)) {
                        latestDocKeyedByOrderId[doc.orderId] = doc.id;
                        latestDoc.push(doc.id);
                    }
                });
            });

            this.latestDocuments = latestDocuments;
        },

        downloadDocuments(documentType) {
            const documentIds = this.latestDocuments[documentType];

            if (!documentIds || documentIds.length === 0) {
                this.createNotificationInfo({
                    message: this.$tc('sw-bulk-edit.modal.success.messageNoDocumentsFound'),
                });

                return Promise.resolve();
            }

            this.$set(this.document[documentType], 'isDownloading', true);
            return this.orderDocumentApiService.download(documentIds)
                .then((response) => {
                    if (!response.data) {
                        return;
                    }

                    const filename = response.headers['content-disposition'].split('filename=')[1];
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.download = filename;
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();

                    this.$set(this.document[documentType], 'isDownloading', false);
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });
                    this.$set(this.document[documentType], 'isDownloading', false);
                });
        },
    },
});

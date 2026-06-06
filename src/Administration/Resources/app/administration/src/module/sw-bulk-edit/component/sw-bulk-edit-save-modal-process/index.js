/**
 * @sw-package framework
 */
import Criteria from 'src/core/data/criteria.data';
import template from './sw-bulk-edit-save-modal-process.html.twig';
import './sw-bulk-edit-save-modal-process.scss';

const { chunk: chunkArray } = Shopware.Utils.array;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'orderDocumentApiService',
        'repositoryFactory',
        'syncService',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    emits: [
        'changes-apply',
        'title-set',
        'buttons-update',
        'redirect',
    ],

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
            maxDependentDocumentsToShow: 10,
        };
    },

    computed: {
        selectedIds() {
            return Shopware.Store.get('swBulkEdit').selectedIds;
        },

        documentTypes() {
            return Shopware.Store.get('swBulkEdit')?.orderDocuments?.download?.value;
        },

        deleteDocumentTypes() {
            return (
                Shopware.Store.get('swBulkEdit')?.orderDocuments?.delete?.value?.filter(
                    (documentType) => documentType.selected,
                ) ?? []
            );
        },

        documentTypeConfigs() {
            return Shopware.Store.get('swBulkEdit').documentTypeConfigs;
        },

        selectedDocumentTypes() {
            if (!this.documentTypeConfigs || this.documentTypeConfigs.length <= 0) {
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

        documentRepository() {
            return this.repositoryFactory.create('document');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            this.setTitle();
            try {
                await this.createDocuments();
                await this.deleteDocuments();
                this.$emit('changes-apply');
            } catch {
                this.$emit('redirect', 'error');
            }
        },

        setTitle() {
            this.$emit('title-set', this.$t('sw-bulk-edit.modal.process.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'cancel',
                    label: this.$t('global.default.cancel'),
                    position: 'left',
                    action: '',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$t('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: true,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        async createDocuments() {
            if (this.createDocumentPayload.length <= 0) {
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
            if (payload.length <= this.requestsPerPayload) {
                await this.orderDocumentApiService.generate(documentType, payload);
                this.document[documentType].isReached = 100;

                return Promise.resolve();
            }

            const chunkedPayload = chunkArray(payload, this.requestsPerPayload);
            const percentages = Math.round(100 / chunkedPayload.length);

            return Promise.all(
                chunkedPayload.map(async (item) => {
                    await this.orderDocumentApiService.generate(documentType, item);
                    this.document[documentType].isReached = this.document[documentType].isReached + percentages;
                }),
            ).then(() => {
                this.document[documentType].isReached = 100;
            });
        },

        async deleteDocuments() {
            if (this.deleteDocumentTypes.length === 0) {
                return;
            }

            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equalsAny('orderId', this.selectedIds));
            criteria.addFilter(
                Criteria.equalsAny(
                    'documentType.technicalName',
                    this.deleteDocumentTypes.map((documentType) => documentType.technicalName),
                ),
            );

            const documents = await this.documentRepository.searchIds(criteria);

            if (documents.total === 0) {
                return;
            }

            const syncPayload = {
                'delete-order_document': {
                    action: 'delete',
                    entity: 'document',
                    payload: documents.data.map((id) => ({ id })),
                },
            };

            try {
                await this.syncService.sync(
                    syncPayload,
                    {},
                    {
                        'single-operation': 1,
                        'sw-language-id': Shopware.Context.api.languageId,
                    },
                );
            } catch (error) {
                const detailedErrorMessage = error.response?.data?.errors?.[0]?.detail;
                this.createNotificationError({
                    message: detailedErrorMessage ? this.truncateErrorMessage(detailedErrorMessage) : error.message,
                });

                throw error;
            }
        },

        truncateErrorMessage(detailedErrorMessage) {
            const dependentDocuments = detailedErrorMessage.split(', ');

            if (dependentDocuments.length <= this.maxDependentDocumentsToShow) {
                return detailedErrorMessage;
            }

            const remainingDependentDocuments = dependentDocuments.length - this.maxDependentDocumentsToShow;

            return `${dependentDocuments.slice(0, this.maxDependentDocumentsToShow).join(', ')}
                ... (and ${remainingDependentDocuments} more)`;
        },
    },
};

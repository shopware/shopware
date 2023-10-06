/**
 * @package system-settings
 */
import template from './sw-bulk-edit-save-modal-success.html.twig';
import './sw-bulk-edit-save-modal-success.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
                    isDownloading: false,
                },
                storno: {
                    isDownloading: false,
                },
                delivery_note: {
                    isDownloading: false,
                },
                credit_note: {
                    isDownloading: false,
                },
            },
        };
    },

    computed: {
        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        downloadOrderDocuments() {
            return Shopware.State.get('swBulkEdit')?.orderDocuments?.download;
        },

        latestDocumentsCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equalsAny('documentTypeId', this.selectedDocumentTypes.map(item => item.id)));
            criteria.addFilter(Criteria.equalsAny('orderId', this.selectedIds));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        selectedDocumentTypes() {
            if (!this.downloadOrderDocuments) {
                return [];
            }

            if (!this.downloadOrderDocuments.isChanged) {
                return [];
            }

            if (this.downloadOrderDocuments.value.length <= 0) {
                return [];
            }

            return this.downloadOrderDocuments.value.filter((item) => item.selected);
        },

        description() {
            return this.selectedDocumentTypes.length > 0
                ? this.$tc('sw-bulk-edit.modal.success.instruction')
                : this.$tc('sw-bulk-edit.modal.success.description');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            this.setTitle();
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

        async getLatestDocuments() {
            if (this.selectedDocumentTypes.length <= 0) {
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

        downloadDocument(documentType) {
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
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.$set(this.document[documentType], 'isDownloading', false);
                });
        },
    },
};

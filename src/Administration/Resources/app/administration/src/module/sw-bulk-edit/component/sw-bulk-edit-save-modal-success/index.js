import template from './sw-bulk-edit-save-modal-success.html.twig';
import './sw-bulk-edit-save-modal-success.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-bulk-edit-save-modal-success', {
    template,

    inject: ['repositoryFactory', 'orderDocumentApiService'],

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

    data() {
        return {
            latestDocuments: {},
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
            if (this.selectedDocumentTypes.length === 0) {
                return;
            }

            this.$emit('order-document-download', true);

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

            this.$emit('order-document-download', false);

            this.latestDocuments = latestDocuments;
        },

        async onDownloadOrderDocuments(technicalName) {
            this.$emit('order-document-download', true);

            const docIds = this.latestDocuments[technicalName];

            if (!docIds || docIds.length === 0) {
                this.$emit('order-document-download', false);

                this.createNotificationInfo({
                    message: this.$tc('sw-bulk-edit.modal.success.messageNoDocumentAvailable'),
                });

                return Promise.resolve();
            }

            return this.orderDocumentApiService.download(docIds)
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

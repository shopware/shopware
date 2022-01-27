import template from './sw-bulk-edit-order.html.twig';
import './sw-bulk-edit-order.scss';
import swBulkEditState from '../../state/sw-bulk-edit.state';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { intersectionBy, chunk, uniqBy } = Shopware.Utils.array;

Component.register('sw-bulk-edit-order', {
    template,

    inject: [
        'bulkEditApiFactory',
        'repositoryFactory',
        'feature',
        'orderDocumentApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            bulkEditData: {},
            isStatusSelected: false,
            isStatusMailsSelected: false,
            orderStatus: [],
            transactionStatus: [],
            deliveryStatus: [],
            itemsPerRequest: 100,
            processStatus: '',
            order: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'order'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        orderDocuments() {
            return Shopware.State.get('swBulkEdit').orderDocuments;
        },

        statusFormFields() {
            let fields = [
                {
                    name: 'orderTransactions',
                    config: {
                        componentName: 'sw-single-select',
                        changeLabel: this.$tc('sw-bulk-edit.order.status.payment.label'),
                        entity: 'state_machine_state',
                        placeholder: this.$tc('sw-bulk-edit.order.status.payment.placeholder'),
                        options: this.transactionStatus,
                    },
                },
                {
                    name: 'orderDeliveries',
                    config: {
                        componentName: 'sw-single-select',
                        changeLabel: this.$tc('sw-bulk-edit.order.status.shipping.label'),
                        entity: 'state_machine_state',
                        placeholder: this.$tc('sw-bulk-edit.order.status.shipping.placeholder'),
                        options: this.deliveryStatus,
                    },
                },
                {
                    name: 'orders',
                    config: {
                        componentName: 'sw-single-select',
                        changeLabel: this.$tc('sw-bulk-edit.order.status.order.label'),
                        entity: 'state_machine_state',
                        placeholder: this.$tc('sw-bulk-edit.order.status.order.placeholder'),
                        options: this.orderStatus,
                    },
                },
            ];

            if (this.feature.isActive('FEATURE_NEXT_17261')) {
                fields = [
                    ...fields,
                    {
                        name: 'statusMails',
                        labelHelpText: this.$tc('sw-bulk-edit.order.status.statusMails.helpText'),
                        config: {
                            hidden: true,
                            changeLabel: this.$tc('sw-bulk-edit.order.status.statusMails.label'),
                        },
                    },
                    {
                        name: 'documents',
                        labelHelpText: this.$tc('sw-bulk-edit.order.status.documents.helpText'),
                        config: {
                            componentName: 'sw-bulk-edit-order-documents',
                            changeLabel: this.$tc('sw-bulk-edit.order.status.documents.label'),
                        },
                    },
                ];
            }

            return fields;
        },

        documentsFormFields() {
            return [
                {
                    name: 'generateInvoice',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateInvoice.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-invoice',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateInvoice.label'),
                    },
                },
                {
                    name: 'generateCancellationInvoice',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-cancellation-invoice',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.label'),
                        changeSubLabel: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.changeSubLabel'),
                    },
                },
                {
                    name: 'generateDeliveryNote',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateDeliveryNote.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-delivery-note',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateDeliveryNote.label'),
                    },
                },
                {
                    name: 'generateCreditNote',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-credit-note',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.label'),
                        changeSubLabel: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.changeSubLabel'),
                    },
                },
            ];
        },

        tagsFormFields() {
            return [
                {
                    name: 'tags',
                    config: {
                        componentName: 'sw-entity-tag-select',
                        entityCollection: this.order.tags,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.order.tags.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.order.tags.placeholder'),
                    },
                },
            ];
        },
    },

    watch: {
        bulkEditData: {
            handler(value) {
                const { orders, orderTransactions, orderDeliveries, statusMails } = value;
                this.isStatusSelected = (orders.isChanged && orders.value)
                    || (orderTransactions.isChanged && orderTransactions.value)
                    || (orderDeliveries.isChanged && orderDeliveries.value);

                this.isStatusMailsSelected = statusMails.isChanged;
            },
            deep: true,
        },

        isStatusSelected() {
            if (!this.isStatusSelected) {
                this.bulkEditData.statusMails.isChanged = false;
            }

            this.bulkEditData.statusMails.disabled = !this.isStatusSelected;
        },

        isStatusMailsSelected() {
            if (!this.isStatusMailsSelected) {
                this.bulkEditData.documents.isChanged = false;
            }

            this.bulkEditData.documents.disabled = !this.isStatusMailsSelected;
        },
    },

    beforeCreate() {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swBulkEdit');
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            this.order = this.orderRepository.create(Shopware.Context.api);

            this.bulkEditService = Shopware.Service('bulkEditService');

            await Promise.all([
                this.fetchStatusOptions('orders.id'),
                this.fetchStatusOptions('orderTransactions.order.id'),
                this.fetchStatusOptions('orderDeliveries.order.id'),
                this.loadCustomFieldSets(),
            ]);

            this.isLoading = false;
            this.isLoadedData = true;

            this.loadBulkEditData();
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.statusFormFields,
                this.documentsFormFields,
                this.tagsFormFields,
            ];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.$set(this.bulkEditData, bulkEditForm.name, {
                        isChanged: false,
                        type: 'overwrite',
                        value: null,
                    });
                });
            });

            this.$set(this.bulkEditData, 'customFields', {
                type: 'overwrite',
                value: null,
            });

            this.$set(this.bulkEditData, 'statusMails', {
                ...this.bulkEditData.statusMails,
                disabled: true,
            });

            this.$set(this.bulkEditData, 'documents', {
                ...this.bulkEditData.documents,
                disabled: true,
            });

            this.order.documents = {
                documentType: {},
                skipSentDocuments: null,
            };
        },

        fetchStatusOptions(field) {
            return this.fetchStateMachineStates(field).then(states => {
                return this.fetchToStateMachineTransitions(states);
            }).then(toStates => {
                switch (field) {
                    case 'orderTransactions.order.id':
                        this.transactionStatus = toStates;
                        break;
                    case 'orderDeliveries.order.id':
                        this.deliveryStatus = toStates;
                        break;
                    default:
                        this.orderStatus = toStates;
                }
            }).catch(error => this.createNotificationError({
                message: error,
            }));
        },

        fetchStateMachineStates(field) {
            const payloadChunks = chunk(this.selectedIds, this.itemsPerRequest);

            const requests = payloadChunks.map(ids => {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equalsAny(field, ids));

                return this.stateMachineStateRepository.searchIds(criteria);
            });

            return Promise.all(requests).then(responses => {
                let states = [];

                responses.forEach(order => {
                    if (order?.data) {
                        states = [...order.data];
                    }
                });

                return states;
            }).catch(error => this.createNotificationError({
                message: error,
            }));
        },

        fetchToStateMachineTransitions(states) {
            if (!states.length) {
                return Promise.resolve([]);
            }

            return this.stateMachineStateRepository
                .search(this.toStateMachineStatesCriteria(states), Shopware.Context.api)
                .then(response => {
                    if (!response.length) {
                        return [];
                    }

                    const fromStates = response.map(state => {
                        if (state?.fromStateMachineTransitions) {
                            return state.fromStateMachineTransitions;
                        }

                        return null;
                    }).filter(state => state !== null);

                    let entries = intersectionBy(...fromStates, 'actionName')
                        .filter(state => state?.toStateMachineState);

                    entries = uniqBy(entries, entry => {
                        return entry.toStateMachineState.technicalName;
                    });

                    return entries.map(entry => ({
                        label: entry.toStateMachineState.translated.name,
                        value: entry.actionName,
                    }));
                }).catch(error => this.createNotificationError({
                    message: error,
                }));
        },

        toStateMachineStatesCriteria(states) {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equalsAny('id', states));
            criteria.addAssociation('fromStateMachineTransitions.toStateMachineState');

            return criteria;
        },

        onProcessData() {
            const data = {
                statusData: [],
                syncData: [],
            };

            const dataPush = ['orderTransactions', 'orderDeliveries', 'orders'];

            Object.entries(this.bulkEditData).forEach(([key, item]) => {
                if (item.isChanged || (key === 'customFields' && item.value)) {
                    const payload = {
                        field: key,
                        type: item.type,
                        value: item.value,
                    };

                    if (dataPush.includes(key)) {
                        const documentTypes = this.order.documents.documentType;
                        const selectedDocumentTypes = [];
                        Object.keys(documentTypes).forEach(documentTypeName => {
                            if (documentTypes[documentTypeName] === true) {
                                selectedDocumentTypes.push(documentTypeName);
                            }
                        });

                        payload.sendMail = this.bulkEditData?.statusMails?.isChanged;
                        payload.documentTypes = selectedDocumentTypes;
                        payload.skipSentDocuments = this.order.documents.skipSentDocuments;
                        payload.value = this.order?.[key];
                        data.statusData.push(payload);
                    } else if (key !== 'documents' && key !== 'statusMails') {
                        data.syncData.push(payload);
                    }
                }
            });

            return data;
        },

        openModal() {
            this.$router.push({ name: 'sw.bulk.edit.order.save' });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.order' });
        },

        onSave() {
            this.isLoading = true;

            const { statusData, syncData } = this.onProcessData();
            const bulkEditOrderHandler = this.bulkEditApiFactory.getHandler('order');

            const payloadChunks = chunk(this.selectedIds, this.itemsPerRequest);
            const requests = [];

            requests.push(this.createDocument());
            payloadChunks.forEach(payload => {
                if (statusData.length) {
                    requests.push(bulkEditOrderHandler.bulkEditStatus(payload, statusData));
                }

                if (syncData.length) {
                    requests.push(bulkEditOrderHandler.bulkEdit(payload, syncData));
                }
            });

            return Promise.all(requests)
                .then(() => {
                    this.processStatus = 'success';
                }).catch((e) => {
                    console.error(e);
                    this.processStatus = 'fail';
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        createDocument() {
            if (!this.feature.isActive('FEATURE_NEXT_17261')) {
                return Promise.resolve();
            }

            return this.orderDocumentApiService.create({
                fileType: 'pdf',
                orderIds: this.selectedIds,
                documentTypeConfigs: this.orderDocuments,
            });
        },

        loadCustomFieldSets() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(value) {
            this.bulkEditData.customFields.value = value;
        },
    },
});


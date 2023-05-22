import template from './sw-bulk-edit-order.html.twig';
import './sw-bulk-edit-order.scss';
import swBulkEditState from '../../state/sw-bulk-edit.state';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;
const { intersectionBy, chunk, uniqBy } = Shopware.Utils.array;

/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            customFieldSets: [],
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
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'order'));

            return criteria;
        },

        hasChanges() {
            const customFieldsValue = this.bulkEditData.customFields?.value;
            const hasFieldsChanged = Object.values(this.bulkEditData).some((field) => field.isChanged);
            const hasCustomFieldsChanged = !types.isEmpty(customFieldsValue) && Object.keys(customFieldsValue).length > 0;

            return hasFieldsChanged || hasCustomFieldsChanged;
        },

        restrictedFields() {
            let restrictedFields = [];

            if (this.$route.params.excludeDelivery === '1') {
                restrictedFields = restrictedFields.concat([
                    'orderDeliveries',
                ]);
            }

            return restrictedFields;
        },

        statusFormFields() {
            const fields = [
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
                        documents: this.bulkEditData?.documents,
                    },
                },
            ];

            return fields.filter((field) => {
                return !this.restrictedFields.includes(field.name);
            });
        },

        documentsFormFields() {
            return [
                {
                    name: 'invoice',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateInvoice.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-invoice',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateInvoice.label'),
                    },
                },
                {
                    name: 'storno',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-cancellation-invoice',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.label'),
                        changeSubLabel: this.$tc('sw-bulk-edit.order.documents.generateCancellationInvoice.changeSubLabel'),
                    },
                },
                {
                    name: 'delivery_note',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateDeliveryNote.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-delivery-note',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateDeliveryNote.label'),
                    },
                },
                {
                    name: 'credit_note',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-generate-credit-note',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.label'),
                        changeSubLabel: this.$tc('sw-bulk-edit.order.documents.generateCreditNote.changeSubLabel'),
                    },
                },
                {
                    name: 'download',
                    labelHelpText: this.$tc('sw-bulk-edit.order.documents.downloadDocuments.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents-download-documents',
                        changeLabel: this.$tc('sw-bulk-edit.order.documents.downloadDocuments.label'),
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
                    || (orderDeliveries?.isChanged && orderDeliveries.value);

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
            this.setRouteMetaModule();

            this.isLoading = true;

            this.order = this.orderRepository.create(Shopware.Context.api);

            await Promise.all([
                this.fetchStatusOptions('orders.id'),
                this.fetchStatusOptions('orderTransactions.orderId'),
                this.fetchStatusOptions('orderDeliveries.orderId'),
                this.loadCustomFieldSets(),
            ]);

            this.isLoading = false;
            this.isLoadedData = true;

            this.loadBulkEditData();
        },

        setRouteMetaModule() {
            this.$set(this.$route.meta.$module, 'color', '#A092F0');
            this.$set(this.$route.meta.$module, 'icon', 'regular-shopping-bag');
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
                skipSentDocuments: true,
            };
        },

        fetchStatusOptions(field) {
            return this.fetchStateMachineStates(field).then(states => {
                return this.fetchToStateMachineTransitions(states);
            }).then(toStates => {
                switch (field) {
                    case 'orderTransactions.orderId':
                        this.transactionStatus = toStates;
                        break;
                    case 'orderDeliveries.orderId':
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

            let versionField = null;

            switch (field) {
                case 'orderTransactions.orderId':
                    versionField = 'orderTransactions.orderVersionId';
                    break;
                case 'orderDeliveries.orderId':
                    versionField = 'orderDeliveries.orderVersionId';
                    break;
                default:
                    versionField = 'orders.versionId';
            }

            const requests = payloadChunks.map(ids => {
                const criteria = new Criteria(1, null);

                criteria.addFilter(Criteria.multi('AND', [
                    Criteria.equalsAny(field, ids),
                    Criteria.equals(versionField, Shopware.Context.api.liveVersionId),
                ]));

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
            const criteria = new Criteria(1, 25);

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
                        const documentTypes = this.order?.documents?.documentType;

                        if (this.bulkEditData?.documents?.isChanged) {
                            const selectedDocumentTypes = Object.keys(documentTypes).filter(
                                documentTypeName => documentTypes[documentTypeName] === true,
                            );

                            if (selectedDocumentTypes.length > 0) {
                                payload.documentTypes = selectedDocumentTypes;
                                payload.skipSentDocuments = this.order.documents.skipSentDocuments;
                            }
                        }

                        payload.sendMail = this.bulkEditData?.statusMails?.isChanged;
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
                })
                .catch(() => {
                    this.processStatus = 'fail';
                })
                .finally(() => {
                    this.isLoading = false;
                    this.getLatestOrderStatus().finally(() => {
                        this.isLoading = false;
                    });
                });
        },

        getLatestOrderStatus() {
            const promises = [];

            if (this.bulkEditData.orderTransactions.isChanged) {
                promises.push(this.fetchStatusOptions('orderTransactions.order.id'));
            }
            if (this.bulkEditData.orderDeliveries?.isChanged) {
                promises.push(this.fetchStatusOptions('orderDeliveries.order.id'));
            }
            if (this.bulkEditData.orders.isChanged) {
                promises.push(this.fetchStatusOptions('orders.id'));
            }

            if (promises.length === 0) {
                return Promise.resolve();
            }

            this.isLoading = true;

            return Promise.all(promises);
        },

        loadCustomFieldSets() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(value) {
            this.bulkEditData.customFields.value = value;
        },

        onChangeDocument(type, isChanged) {
            Shopware.State.commit('swBulkEdit/setOrderDocumentsIsChanged', {
                type,
                isChanged,
            });
        },
    },
};

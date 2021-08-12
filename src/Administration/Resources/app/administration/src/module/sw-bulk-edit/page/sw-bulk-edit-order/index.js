import template from './sw-bulk-edit-order.html.twig';
import './sw-bulk-edit-order.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { intersectionBy, chunk, uniqBy } = Shopware.Utils.array;

Component.register('sw-bulk-edit-order', {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            bulkEditData: {},
            isStatusSelected: false,
            isStatusMailsSelected: false,
            orderStatus: [],
            transactionStatus: [],
            deliveryStatus: [],
            itemsPerRequest: 100,
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

        statusFormFields() {
            return [
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
                    helpText: this.$tc('sw-bulk-edit.order.status.statusMails.helpText'),
                    config: {
                        hidden: true,
                        changeLabel: this.$tc('sw-bulk-edit.order.status.statusMails.label'),
                    },
                },
                {
                    name: 'documents',
                    helpText: this.$tc('sw-bulk-edit.order.status.documents.helpText'),
                    config: {
                        componentName: 'sw-bulk-edit-order-documents',
                        changeLabel: this.$tc('sw-bulk-edit.order.status.documents.label'),
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

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            this.bulkEditService = Shopware.Service('bulkEditService');

            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            await Promise.all([
                this.fetchStatusOptions('orders.id'),
                this.fetchStatusOptions('orderTransactions.order.id'),
                this.fetchStatusOptions('orderDeliveries.order.id'),
            ]);

            this.isLoading = false;

            this.loadBulkEditData();
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.statusFormFields,
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

            this.$set(this.bulkEditData, 'statusMails', {
                ...this.bulkEditData.statusMails,
                disabled: true,
            });

            this.$set(this.bulkEditData, 'documents', {
                ...this.bulkEditData.documents,
                disabled: true,
                value: {
                    target: null,
                    documentType: {},
                    skipSentDocuments: null,
                },
            });
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
                        value: entry.id,
                        label: entry.toStateMachineState.translated.name,
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
    },
});


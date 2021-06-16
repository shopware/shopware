import template from './sw-order-state-history-card.html.twig';
import '../sw-order-state-change-modal';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-state-history-card', {
    template,

    inject: [
        'orderService',
        'stateMachineService',
        'orderStateMachineService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        title: {
            type: String,
            required: true,
        },
        order: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
    data() {
        return {
            showModal: false,
            orderHistory: [],
            orderOptions: [],
            transactionHistory: [],
            transactionOptions: [],
            deliveryHistory: [],
            deliveryOptions: [],
            statesLoading: true,
            modalConfirmed: false,
            currentActionName: null,
            currentStateType: null,
            technicalName: '',
        };
    },
    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        stateMachineHistoryRepository() {
            return this.repositoryFactory.create('state_machine_history');
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (this.order.transactions[i].stateMachineState.technicalName !== 'cancelled') {
                    return this.order.transactions[i];
                }
            }
            return this.order.transactions.last();
        },

        delivery() {
            return this.order.deliveries[0];
        },

        stateMachineHistoryCriteria() {
            const criteria = new Criteria(1, 50);

            const entityIds = [this.order.id];

            if (this.transaction) {
                entityIds.push(this.transaction.id);
            }

            if (this.delivery) {
                entityIds.push(this.delivery.id);
            }

            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_history.entityId.id',
                    entityIds,
                ),
            );
            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_history.entityName',
                    ['order', 'order_transaction', 'order_delivery'],
                ),
            );
            criteria.addAssociation('fromStateMachineState');
            criteria.addAssociation('toStateMachineState');
            criteria.addAssociation('user');
            criteria.addSorting({ field: 'state_machine_history.createdAt', order: 'ASC' });

            return criteria;
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadHistory();
        },

        loadHistory() {
            this.statesLoading = true;
            this.modalConfirmed = false;

            Promise.all([
                this.getStateHistoryEntries(),
                this.getTransitionOptions(),
            ]).then(() => {
                this.$emit('options-change', 'order.states', this.orderOptions);
                if (this.transaction) {
                    this.$emit('options-change', 'order_transaction.states', this.transactionOptions);
                }
                if (this.delivery) {
                    this.$emit('options-change', 'order_delivery.states', this.deliveryOptions);
                }
            }).catch((error) => {
                this.createNotificationError(error);
            }).finally(() => {
                this.statesLoading = false;
            });
        },

        getStateHistoryEntries() {
            return this.stateMachineHistoryRepository.search(this.stateMachineHistoryCriteria).then((fetchedEntries) => {
                this.orderHistory = this.buildStateHistory(this.order, fetchedEntries);
                if (this.transaction) {
                    this.transactionHistory = this.buildStateHistory(this.transaction, fetchedEntries);
                }
                if (this.delivery) {
                    this.deliveryHistory = this.buildStateHistory(this.delivery, fetchedEntries);
                }

                return Promise.resolve(fetchedEntries);
            });
        },

        buildStateHistory(entity, allEntries) {
            const fetchedEntries = allEntries.filter((entry) => {
                return entry.entityId.id === entity.id;
            });

            // this entity has no state history
            if (fetchedEntries.length === 0) {
                return [{
                    state: entity.stateMachineState,
                    createdAt: entity.createdAt,
                    user: null,
                }];
            }

            const entries = [];
            // Prepend start state
            entries.push({
                state: fetchedEntries[0].fromStateMachineState,
                createdAt: entity.createdAt,
                user: null,
            });

            fetchedEntries.forEach((entry) => {
                entries.push({
                    state: entry.toStateMachineState,
                    createdAt: entry.createdAt,
                    user: entry.user ? entry.user : null,
                });
            });

            return entries;
        },

        getTransitionOptions() {
            const statePromises = [this.stateMachineService.getState('order', this.order.id)];
            if (this.transaction) {
                statePromises.push(this.stateMachineService.getState('order_transaction', this.transaction.id));
            }
            if (this.delivery) {
                statePromises.push(this.stateMachineService.getState('order_delivery', this.delivery.id));
            }

            return Promise.all(
                [
                    this.getAllStates(),
                    ...statePromises,
                ],
            ).then((data) => {
                const allStates = data[0];
                const orderState = data[1];
                this.orderOptions = this.buildTransitionOptions(
                    'order.state',
                    allStates,
                    orderState.data.transitions,
                );

                if (this.transaction) {
                    const orderTransactionState = data[2];
                    this.transactionOptions = this.buildTransitionOptions(
                        'order_transaction.state',
                        allStates,
                        orderTransactionState.data.transitions,
                    );
                }

                if (this.delivery) {
                    const orderDeliveryState = data[3];
                    this.deliveryOptions = this.buildTransitionOptions(
                        'order_delivery.state',
                        allStates,
                        orderDeliveryState.data.transitions,
                    );
                }

                return Promise.resolve();
            });
        },

        getAllStates() {
            return this.stateMachineStateRepository.search(this.stateMachineStateCriteria());
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria();
            criteria.addSorting({ field: 'name', order: 'ASC' });
            criteria.addAssociation('stateMachine');
            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_state.stateMachine.technicalName',
                    ['order.state', 'order_transaction.state', 'order_delivery.state'],
                ),
            );

            return criteria;
        },

        buildTransitionOptions(stateMachineName, allTransitions, possibleTransitions) {
            const entries = allTransitions.filter((entry) => {
                return entry.stateMachine.technicalName === stateMachineName;
            });

            const options = entries.map((state) => {
                return {
                    stateName: state.technicalName,
                    id: null,
                    name: state.translated.name,
                    disabled: true,
                };
            });

            options.forEach((option) => {
                const transitionToState = possibleTransitions.filter((transition) => {
                    return transition.toStateName === option.stateName;
                });
                if (transitionToState.length >= 1) {
                    option.disabled = false;
                    option.id = transitionToState[0].actionName;
                }
            });
            return options;
        },

        onOrderStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            if (this.modalConfirmed === false) {
                this.currentActionName = actionName;
                this.currentStateType = 'orderState';

                this.showModal = true;

                return;
            }
            this.modalConfirmed = false;
        },

        onCancelCreation() {
            this.showModal = false;
        },

        onTransactionStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            if (this.modalConfirmed === false) {
                this.currentActionName = actionName;
                this.currentStateType = 'orderTransactionState';

                this.showModal = true;
                return;
            }
            this.modalConfirmed = false;
        },

        onDeliveryStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            if (this.modalConfirmed === false) {
                this.currentActionName = actionName;
                this.currentStateType = 'orderDeliveryState';

                this.showModal = true;
                return;
            }
            this.modalConfirmed = false;
        },

        onLeaveModalClose() {
            this.modalConfirmed = false;
            this.currentActionName = null;
            this.currentStateType = null;
            this.showModal = false;
        },

        onLeaveModalConfirm(docIds, sendMail = true) {
            this.showModal = false;
            if (this.currentStateType === 'orderTransactionState') {
                this.orderStateMachineService.transitionOrderTransactionState(
                    this.transaction.id,
                    this.currentActionName,
                    { documentIds: docIds, sendMail },
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            } else if (this.currentStateType === 'orderState') {
                this.orderStateMachineService.transitionOrderState(
                    this.order.id,
                    this.currentActionName,
                    { documentIds: docIds, sendMail },
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            } else if (this.currentStateType === 'orderDeliveryState') {
                this.orderStateMachineService.transitionOrderDeliveryState(
                    this.delivery.id,
                    this.currentActionName,
                    { documentIds: docIds, sendMail },
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            }
            this.currentActionName = null;
            this.currentStateType = null;
        },

        createStateChangeErrorNotification(errorMessage) {
            this.createNotificationError({
                message: this.$tc('sw-order.stateCard.labelErrorStateChange') + errorMessage,
            });
        },
    },
});

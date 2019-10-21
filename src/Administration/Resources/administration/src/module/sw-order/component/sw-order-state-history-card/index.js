import template from './sw-order-state-history-card.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-state-history-card', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'orderService',
        'stateMachineService',
        'repositoryFactory',
        'apiContext'
    ],
    props: {
        title: {
            type: String,
            required: true
        },
        order: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },
    data() {
        return {
            orderHistory: [],
            orderOptions: [],
            transactionHistory: [],
            transactionOptions: [],
            statesLoading: true
        };
    },
    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineHistoryRepository() {
            return this.repositoryFactory.create('state_machine_history');
        },

        transaction() {
            return this.order.transactions[0];
        },

        stateMachineHistoryCriteria() {
            const criteria = new Criteria(1, 50);

            if (this.transaction) {
                criteria.addFilter(
                    Criteria.equalsAny(
                        'state_machine_history.entityId.id',
                        [this.order.id, this.transaction.id]
                    )
                );
            }

            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_history.entityName',
                    ['order', 'order_transaction']
                )
            );
            criteria.addAssociation('fromStateMachineState');
            criteria.addAssociation('toStateMachineState');
            criteria.addAssociation('user');
            criteria.addSorting({ field: 'state_machine_history.createdAt', order: 'ASC' });

            return criteria;
        }

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

            Promise.all([
                this.getStateHistoryEntries(),
                this.getTransitionOptions()
            ]).then(() => {
                this.$emit('options-change', 'order.states', this.orderOptions);
                if (this.transaction) {
                    this.$emit('options-change', 'order_transaction.states', this.transactionOptions);
                }
            }).catch((error) => {
                this.createNotificationError(error);
            }).finally(() => {
                this.statesLoading = false;
            });
        },

        getStateHistoryEntries() {
            return this.stateMachineHistoryRepository.search(
                this.stateMachineHistoryCriteria,
                this.apiContext
            ).then((fetchedEntries) => {
                this.orderHistory = this.buildStateHistory(this.order, fetchedEntries);
                if (this.transaction) {
                    this.transactionHistory = this.buildStateHistory(this.transaction, fetchedEntries);
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
                    user: null
                }];
            }

            const entries = [];
            // Prepend start state
            entries.push({
                state: fetchedEntries[0].fromStateMachineState,
                createdAt: entity.createdAt,
                user: null
            });

            fetchedEntries.forEach((entry) => {
                entries.push({
                    state: entry.toStateMachineState,
                    createdAt: entry.createdAt,
                    user: entry.user ? entry.user : null
                });
            });

            return entries;
        },

        getTransitionOptions() {
            const statePromises = [this.stateMachineService.getState('order', this.order.id)];
            if (this.transaction) {
                statePromises.push(this.stateMachineService.getState('order_transaction', this.transaction.id));
            }

            return Promise.all(
                [
                    this.getAllStates(),
                    ...statePromises
                ]
            ).then((data) => {
                const allStates = data[0];
                const orderState = data[1];
                this.orderOptions = this.buildTransitionOptions(
                    'order.state',
                    allStates,
                    orderState.data.transitions
                );

                if (this.transaction) {
                    const orderTransactionState = data[2];
                    this.transactionOptions = this.buildTransitionOptions(
                        'order_transaction.state',
                        allStates,
                        orderTransactionState.data.transitions
                    );
                }

                return Promise.resolve();
            });
        },

        getAllStates() {
            return this.stateMachineStateRepository.search(this.stateMachineStateCriteria(), this.apiContext);
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria();
            criteria.addSorting({ field: 'name', order: 'ASC' });
            criteria.addAssociation('stateMachine');
            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_state.stateMachine.technicalName',
                    ['order.state', 'order_transaction.state']
                )
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
                    disabled: true
                };
            });

            options.forEach((option) => {
                const transitionToState = possibleTransitions.filter((transition) => {
                    return transition.toStateName === option.stateName;
                });
                if (transitionToState.length === 1) {
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

            this.stateMachineService.transitionState('order', this.order.id, actionName).then(() => {
                this.$emit('order-state-change');
                this.loadHistory();
            }).catch((error) => {
                this.createStateChangeErrorNotification(error);
            });
        },

        onTransactionStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            this.stateMachineService.transitionState(
                'order_transaction',
                this.transaction.id,
                actionName
            ).then(() => {
                this.$emit('order-state-change');
                this.loadHistory();
            }).catch((error) => {
                this.createStateChangeErrorNotification(error);
            });
        },

        createStateChangeErrorNotification(errorMessage) {
            this.createNotificationError({
                title: this.$tc('sw-order.stateCard.headlineErrorStateChange'),
                message: this.$tc('sw-order.stateCard.labelErrorStateChange') + errorMessage
            });
        }
    }
});

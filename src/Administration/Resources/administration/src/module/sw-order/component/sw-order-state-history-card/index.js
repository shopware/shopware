import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-order-state-history-card.html.twig';


Component.register('sw-order-state-history-card', {
    template,
    mixins: [
        Mixin.getByName('notification')
    ],
    inject: ['orderService', 'orderTransactionService'],
    props: {
        title: {
            type: String,
            required: true,
            default() {
                return '';
            }
        },
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
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
        stateMachineStateStore() {
            return State.getStore('state_machine_state');
        },
        stateMachineHistoryStore() {
            return State.getStore('state_machine_history');
        }
    },
    watch: {
        'isLoading'() {
            if (!this.isLoading) this.createdComponent();
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

            // Order
            const orderHistory = this.getStateHistoryEntries(this.order).then((entries) => {
                this.orderHistory = entries;
                return Promise.resolve();
            });

            const orderTransitions = this.orderService.getState(this.order.id, this.order.versionId).then((response) => {
                return this.getStateTransitionOptions('order.state', response.data.transitions);
            }).then((options) => {
                this.orderOptions = options;
                return Promise.resolve();
            });

            // Order Transaction
            const transactionHistory = this.getStateHistoryEntries(this.order.transactions[0]).then((entries) => {
                this.transactionHistory = entries;
                return Promise.resolve();
            });

            const transactionTransitions = this.orderTransactionService.getState(
                this.order.transactions[0].id,
                this.order.versionId
            ).then((response) => {
                return this.getStateTransitionOptions('order_transaction.state', response.data.transitions);
            }).then((options) => {
                this.transactionOptions = options;
                return Promise.resolve();
            });

            Promise.all([
                orderHistory,
                orderTransitions,
                transactionHistory,
                transactionTransitions
            ]).then(() => {
                this.statesLoading = false;
                this.$emit('state-transition-options-changed', 'order.states', this.orderOptions);
                this.$emit('state-transition-options-changed', 'order_transaction.states', this.transactionOptions);
            });
        },
        getStateHistoryEntries(entity) {
            const criteria = CriteriaFactory.multi('AND',
                CriteriaFactory.equals('state_machine_history.entityId.id', entity.id),
                CriteriaFactory.contains('state_machine_history.entityName', entity.entityName));

            return this.stateMachineHistoryStore.getList({
                limit: 50,
                page: 1,
                sortBy: 'state_machine_history.createdAt',
                sortDirection: 'ASC',
                versionId: this.order.versionId,
                criteria: criteria
            }).then((fetchedEntries) => {
                // This order has no history entries
                if (fetchedEntries.total === 0) {
                    return [{
                        state: entity.stateMachineState,
                        createdAt: entity.createdAt,
                        user: null
                    }];
                }

                const entries = [];
                // Prepend start state
                entries.push({
                    state: fetchedEntries.items[0].fromStateMachineState,
                    createdAt: entity.createdAt,
                    user: null
                });
                fetchedEntries.items.forEach((entry) => {
                    entries.push({
                        state: entry.toStateMachineState,
                        createdAt: entry.createdAt,
                        user: entry.user
                    });
                });

                return entries;
            });
        },
        getStateTransitionOptions(stateMachineName, possibleTransitions) {
            const criteriaState =
                    CriteriaFactory.equals('state_machine_state.stateMachine.technicalName', stateMachineName);

            return this.stateMachineStateStore.getList(
                { criteria: criteriaState }
            ).then((entries) => {
                const options = [];
                entries.items.forEach((state) => {
                    options.push({
                        stateName: state.technicalName,
                        id: null,
                        name: state.meta.viewData.name,
                        disabled: true
                    });
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

                return Promise.resolve(options);
            });
        },
        onOrderStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            this.orderService.transitionState(this.order.id, this.order.versionId, actionName).then(() => {
                this.$emit('order-state-changed');
            }).catch((error) => {
                this.createStateChangeErrorNotification(error);
            });
        },
        onTransactionStateSelected(actionName) {
            if (!actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            this.orderTransactionService.transitionState(this.order.transactions[0].id,
                this.order.versionId, actionName).then(() => {
                this.$emit('order-state-changed');
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

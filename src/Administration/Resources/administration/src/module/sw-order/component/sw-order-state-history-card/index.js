import { Component } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-order-state-history-card.html.twig';


Component.register('sw-order-state-history-card', {
    template,

    inject: ['stateMachineHistoryService'],
    data() {
        return {
            orderStateHistory: [],
            transactionStateHistory: []
        };
    },
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
    watch: {
        'order.versionId'() {
            this.createdComponent();
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
            this.getStateHistoryEntries(this.order).then((entries) => {
                this.orderStateHistory = entries;
            });

            this.getStateHistoryEntries(this.order.transactions[0]).then((entries) => {
                this.transactionStateHistory = entries;
            });
        },
        getStateHistoryEntries(entity) {
            const criteria = CriteriaFactory.multi('AND',
                CriteriaFactory.equals('state_machine_history.entityId.id', entity.id),
                CriteriaFactory.contains('state_machine_history.entityName', entity.entityName));

            return this.stateMachineHistoryService.getList({
                limit: 50,
                page: 1,
                sortBy: 'state_machine_history.createdAt',
                sortDirection: 'ASC',
                versionId: this.order.versionId,
                criteria: criteria
            }).then((fetchedEntries) => {
                const entries = [];

                // This order has no history entries
                if (fetchedEntries.meta.total === 0) {
                    entries.push({
                        stateMachineName: `${entity.entityName}.state`,
                        state: entity.stateMachineState,
                        createdAt: entity.createdAt,
                        user: null
                    });
                    return entries;
                }

                // Prepend start state
                entries.push({
                    stateMachineName: `${entity.entityName}.state`,
                    state: fetchedEntries.data[0].fromStateMachineState,
                    createdAt: entity.createdAt,
                    user: null
                });
                fetchedEntries.data.forEach((entry) => {
                    entries.push({
                        stateMachineName: `${entity.entityName}.state`,
                        state: entry.toStateMachineState,
                        createdAt: entry.createdAt,
                        user: entry.user
                    });
                });

                return entries;
            });
        }
    }
});

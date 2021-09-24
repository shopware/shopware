import template from './sw-order-state-history-modal.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-state-history-modal', {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
    ],

    mixins: [
        'notification',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            dataSource: [],
            statesLoading: true,
        };
    },

    computed: {
        stateMachineHistoryRepository() {
            return this.repositoryFactory.create('state_machine_history');
        },

        stateMachineHistoryCriteria() {
            const criteria = new Criteria(1, 50);

            const entityIds = [
                this.order.id,
                ...this.order.transactions.map((transaction) => {
                    return transaction.id;
                }),
                ...this.order.deliveries.map((delivery) => {
                    return delivery.id;
                }),
            ];

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

        columns() {
            return [
                { property: 'createdAt', label: this.$tc('sw-order.stateHistoryModal.column.createdAt') },
                { property: 'entity', label: this.$tc('sw-order.stateHistoryModal.column.entity') },
                { property: 'user', label: this.$tc('sw-order.stateHistoryModal.column.user') },
                { property: 'transaction', label: this.$tc('sw-order.stateHistoryModal.column.transaction') },
                { property: 'delivery', label: this.$tc('sw-order.stateHistoryModal.column.delivery') },
                { property: 'order', label: this.$tc('sw-order.stateHistoryModal.column.order') },
            ];
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

            this.getStateHistoryEntries().catch((error) => {
                this.createNotificationError(error);
            }).finally(() => {
                this.statesLoading = false;
            });
        },

        getStateHistoryEntries() {
            return this.stateMachineHistoryRepository.search(this.stateMachineHistoryCriteria).then((fetchedEntries) => {
                this.dataSource = this.buildStateHistory(fetchedEntries);

                return Promise.resolve(fetchedEntries);
            });
        },

        buildStateHistory(allEntries) {
            const states = {
                order: allEntries.filter((entry) => {
                    return entry.entityName === 'order';
                }).first()?.fromStateMachineState ?? this.order.stateMachineState,
                order_transaction: allEntries.filter((entry) => {
                    return entry.entityName === 'order_transaction';
                }).first()?.fromStateMachineState ?? this.order.transactions.last()?.stateMachineState,
                order_delivery: allEntries.filter((entry) => {
                    return entry.entityName === 'order_delivery';
                }).first()?.fromStateMachineState ?? this.order.deliveries.first()?.stateMachineState,
            };

            const entries = [];
            // Prepend start state
            entries.push(this.createEntry(states, this.order));

            allEntries.forEach((entry) => {
                states[entry.entityName] = entry.toStateMachineState;
                entries.push(this.createEntry(states, entry));
            });

            return entries;
        },

        createEntry(states, entry) {
            return {
                order: states.order,
                transaction: states.order_transaction,
                delivery: states.order_delivery,
                createdAt: entry.createdAt,
                user: entry.user,
                entity: entry.entityName ?? 'order',
            };
        },

        getVariantState(entity, state) {
            return this.stateStyleDataProviderService.getStyle(`${entity}.state`, state.technicalName).variant;
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
});

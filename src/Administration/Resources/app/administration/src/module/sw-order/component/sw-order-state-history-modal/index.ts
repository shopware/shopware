import type { PropType } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-order-state-history-modal.html.twig';

/**
 * @sw-package checkout
 */

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

interface StateMachineHistoryData {
    order: Entity<'state_machine_state'>;
    transaction: Entity<'state_machine_state'>;
    delivery: Entity<'state_machine_state'>;
    createdAt: string;
    user?: {
        username: string;
    };
    integration?: {
        label: string;
    };
    entity: string;
    referencedId?: string;
}

interface CombinedStates {
    order: Entity<'state_machine_state'>;
    ['order_transaction']: Entity<'state_machine_state'>;
    ['order_delivery']: Entity<'state_machine_state'>;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        order: {
            type: Object as PropType<Entity<'order'>>,
            required: true,
        },
        /** @deprecated tag:v6.8.0 - will be removed without replacment */
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        dataSource: StateMachineHistoryData[];
        limit: number;
        page: number;
        total: number;
        steps: number[];
    } {
        return {
            dataSource: [],
            limit: 10,
            page: 1,
            total: 0,
            steps: [
                5,
                10,
                25,
            ],
        };
    },

    computed: {
        stateMachineHistoryRepository(): RepositoryType<'state_machine_history'> {
            return this.repositoryFactory.create('state_machine_history');
        },

        stateMachineHistoryCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            const entityIds = [
                this.order.id,
                ...(this.order.transactions ?? []).map((transaction) => {
                    return transaction.id;
                }),
                ...(this.order.deliveries ?? []).map((delivery) => {
                    return delivery.id;
                }),
            ];

            criteria.addFilter(Criteria.equalsAny('state_machine_history.referencedId', entityIds));
            criteria.addFilter(
                Criteria.equalsAny('state_machine_history.entityName', [
                    'order',
                    'order_transaction',
                    'order_delivery',
                ]),
            );
            criteria.addAssociation('fromStateMachineState');
            criteria.addAssociation('toStateMachineState');
            criteria.addAssociation('user');
            criteria.addAssociation('integration');
            criteria.addSorting({
                field: 'state_machine_history.createdAt',
                order: 'ASC',
                naturalSorting: false,
            });

            return criteria;
        },

        columns(): Array<{ property: string; label: string }> {
            return [
                {
                    property: 'createdAt',
                    label: this.$tc('sw-order.stateHistoryModal.column.createdAt'),
                },
                {
                    property: 'entity',
                    label: this.$tc('sw-order.stateHistoryModal.column.entity'),
                },
                {
                    property: 'user',
                    label: this.$tc('sw-order.stateHistoryModal.column.user'),
                },
                {
                    property: 'transaction',
                    label: this.$tc('sw-order.stateHistoryModal.column.transaction'),
                },
                {
                    property: 'delivery',
                    label: this.$tc('sw-order.stateHistoryModal.column.delivery'),
                },
                {
                    property: 'order',
                    label: this.$tc('sw-order.stateHistoryModal.column.order'),
                },
            ];
        },

        hasMultipleTransactions(): boolean {
            return (this.order?.transactions?.filter((v, idx, a) => a.indexOf(v) === idx)?.length ?? 0) > 1;
        },

        statesLoading: {
            get(): boolean {
                return Shopware.Store.get('swOrderDetail').loading.states;
            },
            set(value: boolean): void {
                Shopware.Store.get('swOrderDetail').setLoading([
                    'states',
                    value,
                ]);
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.loadHistory();
        },

        async loadHistory(): Promise<void> {
            this.statesLoading = true;

            try {
                await this.getStateHistoryEntries();
            } catch (error: unknown) {
                // @ts-expect-error
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                const errorMessage = error?.response?.data?.errors?.[0]?.detail || '';

                this.createNotificationError({
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    message: errorMessage,
                });
            } finally {
                this.statesLoading = false;
            }
        },

        getStateHistoryEntries(): Promise<EntityCollection<'state_machine_history'>> {
            return this.stateMachineHistoryRepository.search(this.stateMachineHistoryCriteria).then((fetchedEntries) => {
                this.dataSource = this.buildStateHistory(fetchedEntries);
                this.total = fetchedEntries.total ?? 1;
                return Promise.resolve(fetchedEntries);
            });
        },

        buildStateHistory(allEntries: EntityCollection<'state_machine_history'>): StateMachineHistoryData[] {
            const states = {
                order:
                    allEntries.filter((entry) => {
                        return entry.entityName === 'order';
                    })[0]?.fromStateMachineState ?? this.order.stateMachineState,
                order_transaction:
                    allEntries.filter((entry) => {
                        return entry.entityName === 'order_transaction';
                    })[0]?.fromStateMachineState ?? this.order.transactions?.last()?.stateMachineState,
                order_delivery:
                    allEntries.filter((entry) => {
                        return entry.entityName === 'order_delivery';
                    })[0]?.fromStateMachineState ?? this.order.deliveries?.first()?.stateMachineState,
            };

            const entries = [] as Array<StateMachineHistoryData>;

            if (this.page === 1) {
                // @ts-expect-error - states exists
                // Prepend start state
                entries.push(this.createEntry(states, this.order));
            }

            const knownTransactionIds: string[] = [];
            allEntries.forEach((entry: Entity<'state_machine_history'>) => {
                if (entry.entityName === 'order_transaction' && !knownTransactionIds.includes(entry.referencedId)) {
                    if (knownTransactionIds.length > 0) {
                        entries.push(
                            this.createEntry(
                                {
                                    ...states,
                                    // @ts-expect-error - states exists
                                    order_transaction: entry.fromStateMachineState,
                                },
                                { ...entry, user: undefined },
                            ),
                        );
                    }

                    knownTransactionIds.push(entry.referencedId);
                }

                // @ts-expect-error - the entityName has to be order, order_transaction or order_delivery
                states[entry.entityName] = entry.toStateMachineState;
                // @ts-expect-error - states exists
                entries.push(this.createEntry(states, entry));
            });

            const lastTransaction = this.order.transactions?.last();
            if (
                !!lastTransaction &&
                !knownTransactionIds.includes(lastTransaction.id) &&
                (this.order.transactions?.length ?? 0) > 1
            ) {
                entries.push(
                    this.createEntry(
                        {
                            ...states,
                            // @ts-expect-error - states exists
                            order_transaction: lastTransaction?.stateMachineState,
                        },
                        lastTransaction,
                    ),
                );
            }

            return entries;
        },

        createEntry(
            states: CombinedStates,
            entry: Entity<'state_machine_history'> | Entity<'order'> | Entity<'order_transaction'>,
        ): StateMachineHistoryData {
            return {
                order: states.order,
                transaction: states.order_transaction,
                delivery: states.order_delivery,
                createdAt: 'orderDateTime' in entry ? entry.orderDateTime : entry.createdAt,
                user: 'user' in entry ? entry.user : undefined,
                integration: 'integration' in entry ? entry.integration : undefined,
                entity: 'entityName' in entry ? entry.entityName : entry.getEntityName(),
                referencedId: 'referencedId' in entry ? entry.referencedId : entry.id,
            };
        },

        getVariantState(entity: string, state: Entity<'state_machine_state'>): string {
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-return
            return this.stateStyleDataProviderService.getStyle(`${entity}.state`, state.technicalName).variant;
        },

        onClose(): void {
            this.$emit('modal-close');
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

            void this.loadHistory();
        },

        enumerateTransaction(item: StateMachineHistoryData): string {
            if (item.entity !== 'order_transaction' || !this.hasMultipleTransactions) {
                return '';
            }

            const idx = this.order.transactions?.findIndex((transaction) => transaction.id === item.referencedId) ?? -1;

            return String(idx >= 0 ? idx + 1 : '');
        },

        getStateChangeAuthor(item: StateMachineHistoryData): string {
            if (item.user) {
                return item.user.username;
            }
            if (item.integration) {
                const integrationLabel = item.integration.label;
                return `${integrationLabel} (${this.$t('sw-order.stateHistoryModal.labelIntegration')})`;
            }

            return this.$t('sw-order.stateHistoryModal.labelSystemUser');
        },
    },
});

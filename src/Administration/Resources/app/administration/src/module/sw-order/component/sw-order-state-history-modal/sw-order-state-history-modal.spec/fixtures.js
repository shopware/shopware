import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

/**
 * `total` defaults to the collection length, but can be passed separately so the repository mock
 * below can report a full count while returning only a slice.
 */
export function getCollection(entity, collection, total = collection.length) {
    return new EntityCollection(`/${entity}`, entity, null, { isShopwareContext: true }, collection, total, null);
}

export const stateHistoryFixture = [
    {
        entityName: 'order_delivery',
        fromStateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
        toStateMachineState: {
            technicalName: 'shipped',
            translated: {
                name: 'Shipped',
            },
        },
        user: {
            username: 'admin',
        },
        createdAt: '2022-10-12T10:01:28.535+00:00',
        internalComment: 'Order delivery internal comment',
    },
    {
        entityName: 'order_transaction',
        fromStateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
        toStateMachineState: {
            technicalName: 'in_progress',
            translated: {
                name: 'In progress',
            },
        },
        user: {
            username: 'admin',
        },
        createdAt: '2022-10-12T10:01:33.815+00:00',
        referencedId: '2',
        internalComment: 'Order transaction internal comment',
    },
];

export const orderProp = {
    id: '1',
    orderDateTime: '2022-10-10T10:01:33.815+00:00',
    transactions: getCollection('order_transaction', [
        {
            id: '2',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_transaction',
        },
        {
            id: '5',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_transaction',
        },
    ]),
    deliveries: getCollection('order_delivery', [
        {
            id: '3',
            stateMachineState: {
                technicalName: 'open',
                translated: {
                    name: 'Open',
                },
            },
            getEntityName: () => 'order_delivery',
        },
    ]),
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
    getEntityName: () => 'order',
};

export async function createWrapper(options = {}, order = orderProp, stateHistory = stateHistoryFixture) {
    return mount(await wrapTestComponent('sw-order-state-history-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-data-grid-skeleton': true,
                'sw-pagination': await wrapTestComponent('sw-pagination', {
                    sync: true,
                }),
                'sw-time-ago': true,
                'sw-label': {
                    template: '<div class="sw-label"><slot></slot></div>',
                },
                'sw-checkbox-field': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-select-field': true,
                'sw-loader': true,
                'sw-provide': { template: `<slot/>`, inheritAttrs: false },
                'mt-icon': true,
            },
            provide: {
                stateStyleDataProviderService: {
                    getStyle: () => {
                        return {
                            variant: '',
                        };
                    },
                },
                repositoryFactory: {
                    create: () => ({
                        search: (criteria) => {
                            if (options.error) {
                                return Promise.reject({
                                    response: {
                                        data: {
                                            errors: [
                                                {
                                                    code: 'This is an error code',
                                                    detail: 'This is an detailed error message',
                                                },
                                            ],
                                        },
                                    },
                                });
                            }

                            // Paginate like the server does, so a component that asks for a
                            // single page cannot silently receive the whole history. `total`
                            // stays the full count, as the server reports it.
                            const entries =
                                criteria?.limit == null
                                    ? stateHistory
                                    : stateHistory.slice(
                                          (criteria.page - 1) * criteria.limit,
                                          criteria.page * criteria.limit,
                                      );

                            return Promise.resolve(getCollection('state_machine_history', entries, stateHistory.length));
                        },
                    }),
                },
            },
        },
        data() {
            return {
                ...options,
            };
        },
        props: {
            isLoading: false,
            order,
        },
    });
}

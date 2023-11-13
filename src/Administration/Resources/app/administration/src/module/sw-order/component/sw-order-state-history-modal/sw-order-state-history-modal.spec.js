import { shallowMount } from '@vue/test-utils';
import swOrderStateHistoryModalComponent from 'src/module/sw-order/component/sw-order-state-history-modal';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-button';
import 'src/app/component/grid/sw-pagination';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package checkout
 */

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

const stateHistoryFixture = [
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
    },
];

const orderProp = {
    id: '1',
    orderDateTime: '2022-10-10T10:01:33.815+00:00',
    transactions: [{
        id: '2',
        stateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
    }],
    deliveries: [{
        id: '3',
        stateMachineState: {
            technicalName: 'open',
            translated: {
                name: 'Open',
            },
        },
    }],
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
};

orderProp.transactions.last = () => ({
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
});

orderProp.deliveries.first = () => ({
    stateMachineState: {
        technicalName: 'open',
        translated: {
            name: 'Open',
        },
    },
});

Shopware.Component.register('sw-order-state-history-modal', swOrderStateHistoryModalComponent);

describe('src/module/sw-order/component/sw-order-state-history-modal', () => {
    let SwOrderStateHistoryModal;

    async function createWrapper(options = {}) {
        return shallowMount(SwOrderStateHistoryModal, {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
                'sw-data-grid-skeleton': true,
                'sw-pagination': await Shopware.Component.build('sw-pagination'),
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-icon': true,
                'sw-time-ago': true,
                'sw-label': {
                    template: '<div class="sw-label"><slot></slot></div>',
                },
            },

            data() {
                return {
                    ...options,
                };
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
                        search: () => {
                            if (options.error) {
                                // eslint-disable-next-line prefer-promise-reject-errors
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

                            return Promise.resolve(getCollection('state_machine_history', stateHistoryFixture));
                        },
                    }),
                },
            },
            propsData: {
                isLoading: false,
                order: orderProp,
            },
        });
    }

    beforeAll(async () => {
        SwOrderStateHistoryModal = await Shopware.Component.build('sw-order-state-history-modal');
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show state history grid correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(stateHistoryRows).toHaveLength(3);

        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order');
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(firstRow.find('.sw-data-grid__cell--delivery').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--order').text()).toBe('Open');

        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_delivery');
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(secondRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(secondRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--order').text()).toBe('Open');

        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction');
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(thirdRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(thirdRow.find('.sw-data-grid__cell--transaction').text()).toBe('In progress');
        expect(thirdRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
    });

    it('should error notification if loading state history failed', async () => {
        const wrapper = await createWrapper({
            error: true,
        });

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await flushPromises();

        expect(notificationMock).toHaveBeenCalled();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should emit modal-close event when clicking on Close button', async () => {
        const wrapper = await createWrapper();
        const closeButton = wrapper.find('.sw-button');

        await closeButton.trigger('click');
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should able to change page', async () => {
        const wrapper = await createWrapper({
            steps: [1],
            limit: 1,
        });

        await flushPromises();

        const pageButtons = wrapper.findAll('.sw-pagination__list-button');
        await pageButtons.at(1).trigger('click');

        expect(pageButtons.at(1).classes()).toContain('is-active');
        expect(wrapper.vm.page).toBe(2);
    });
});

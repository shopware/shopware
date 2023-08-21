import { shallowMount } from '@vue/test-utils';
import swOrderDetailsStateCard from 'src/module/sw-order/component/sw-order-details-state-card';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

jest.useFakeTimers().setSystemTime(new Date(170363865609544));

Shopware.Component.register('sw-order-details-state-card', swOrderDetailsStateCard);

const orderMock = {
    orderNumber: 10000,
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: 'Transaction state',
                },
            },
        },
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: 'Delivery state',
                },
            },
        },
    ],
    stateMachineState: {
        translated: {
            name: 'Order state',
        },
    },
};

orderMock.transactions.last = () => ({
    stateMachineState: {
        translated: {
            name: 'Transaction state',
        },
    },
    getEntityName: () => {
        return 'order_transaction';
    },
});

orderMock.deliveries.first = () => ({
    stateMachineState: {
        translated: {
            name: 'Delivery state',
        },
    },
});

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-details-state-card'), {
        propsData: {
            order: orderMock,
            isLoading: false,
            entity: orderMock.transactions.last(),
        },
        provide: {
            orderStateMachineService: {},
            stateStyleDataProviderService: {
                getStyle: () => {
                    return {
                        placeholder: {
                            icon: 'small-arrow-small-down',
                            iconStyle: 'sw-order-state__bg-neutral-icon',
                            iconBackgroundStyle: 'sw-order-state__bg-neutral-icon-bg',
                            selectBackgroundStyle: 'sw-order-state__bg-neutral-select',
                            variant: 'neutral',
                            colorCode: '#94a6b8',
                        },
                    };
                },
            },
            stateMachineService: {
                getState: () => { return { data: { transitions: [] } }; },
            },
            repositoryFactory: {
                create: (entity) => {
                    return {
                        search: () => {
                            if (entity === 'state_machine_history') {
                                return Promise.resolve({
                                    first: () => {
                                        return {
                                            user: {
                                                firstName: 'John',
                                                lastName: 'Doe',
                                            },
                                            createdAt: new Date(),
                                        };
                                    },
                                });
                            }

                            return Promise.resolve(new EntityCollection(
                                '',
                                '',
                                Shopware.Context.api,
                                null,
                                [],
                                0,
                            ));
                        },
                    };
                },
            },
        },
        stubs: {
            'sw-order-state-select-v2': true,
            'sw-external-link': { template: '<a href="#"></a>' },
            'sw-order-state-change-modal': true,
            'sw-container': true,
            'sw-card': true,
            'sw-time-ago': {
                template: '<div class="sw-time-ago"></div>',
                props: ['date'],
            },
            i18n: { template: '<span><slot name="time"></slot><slot name="author"></slot></span>' },
        },
    });
}

describe('src/module/sw-order/component/sw-order-details-state-card', () => {
    beforeEach(async () => {
        if (Shopware.State.get('swOrderDetail')) {
            Shopware.State.unregisterModule('swOrderDetail');
        }

        Shopware.State.registerModule('swOrderDetail', {
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
            },
        });
    });

    it('should show history text', async () => {
        global.repositoryFactoryMock.showError = false;

        const wrapper = await createWrapper();
        await flushPromises();

        const summary = wrapper.get('.sw-order-detail-state-card__state-history-text');

        expect(summary.text()).toBe('John Doe');
        expect(summary.get('.sw-time-ago').props('date')).toEqual(new Date(170363865609544));
    });
});

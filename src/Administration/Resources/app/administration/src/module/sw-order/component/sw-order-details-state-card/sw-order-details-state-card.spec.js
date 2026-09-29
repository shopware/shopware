import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

jest.useFakeTimers().setSystemTime(new Date(170363865609544));

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

const lastStateChangeByAdminUser = {
    user: {
        firstName: 'John',
        lastName: 'Doe',
    },
    createdAt: new Date(),
};

async function createWrapper(lastStateChange = lastStateChangeByAdminUser) {
    return mount(await wrapTestComponent('sw-order-details-state-card', { sync: true }), {
        props: {
            order: orderMock,
            isLoading: false,
            entity: orderMock.transactions.last(),
        },
        global: {
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
                                colorCode: 'var(--color-icon-secondary-default)',
                            },
                        };
                    },
                },
                stateMachineService: {
                    getState: () => {
                        return { data: { transitions: [] } };
                    },
                },
                repositoryFactory: {
                    create: (entity) => {
                        return {
                            search: () => {
                                if (entity === 'state_machine_history') {
                                    return Promise.resolve({
                                        first: () => lastStateChange,
                                    });
                                }

                                return Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, [], 0));
                            },
                        };
                    },
                },
                swOrderDetailAskAndSaveEdits: () => Promise.resolve(true),
            },
            stubs: {
                'sw-order-state-select-v2': true,
                'sw-external-link': { template: '<a href="#"></a>' },
                'sw-order-state-change-modal': true,
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-time-ago': {
                    template: '<div class="sw-time-ago"></div>',
                    props: ['date'],
                },
                'i18n-t': {
                    template: '<span><slot name="time"></slot><slot name="author"></slot></span>',
                },
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'mt-icon': true,
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-details-state-card', () => {
    beforeEach(async () => {
        Shopware.Store.unregister('swOrderDetail');
        Shopware.Store.register({
            id: 'swOrderDetail',
            state: () => ({
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
            }),
        });
    });

    it('should show history text', async () => {
        global.repositoryFactoryMock.showError = false;

        const wrapper = await createWrapper();
        await flushPromises();

        const summary = wrapper.get('.sw-order-detail-state-card__state-history-text');

        expect(summary.text()).toBe('John Doe');
        expect(summary.findComponent('.sw-time-ago').props('date')).toEqual(new Date(170363865609544));
    });

    it('should show the customer as author when the state was changed from the storefront', async () => {
        global.repositoryFactoryMock.showError = false;

        const wrapper = await createWrapper({
            sourceType: 'sales-channel',
            createdAt: new Date(),
        });
        await flushPromises();

        expect(wrapper.get('.sw-order-detail-state-card__state-history-text').text()).toBe(
            'sw-order.stateCard.labelCustomer',
        );
    });

    it('should fall back to the system author for internal state changes', async () => {
        global.repositoryFactoryMock.showError = false;

        const wrapper = await createWrapper({
            sourceType: 'system',
            createdAt: new Date(),
        });
        await flushPromises();

        expect(wrapper.get('.sw-order-detail-state-card__state-history-text').text()).toBe(
            'sw-order.stateCard.labelSystemUser',
        );
    });
});

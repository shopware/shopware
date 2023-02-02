import { shallowMount } from '@vue/test-utils';
import swOrderDetailsStateCard from 'src/module/sw-order/component/sw-order-details-state-card';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-details-state-card', swOrderDetailsStateCard);

const orderMock = {
    orderNumber: 10000,
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: 'Transaction state'
                }
            }
        }
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: 'Delivery state'
                }
            }
        }
    ],
    stateMachineState: {
        translated: {
            name: 'Order state'
        }
    }
};

orderMock.transactions.last = () => ({
    stateMachineState: {
        translated: {
            name: 'Transaction state'
        }
    },
    getEntityName: () => {
        return 'order_transaction';
    }
});

orderMock.deliveries.first = () => ({
    stateMachineState: {
        translated: {
            name: 'Delivery state'
        }
    }
});

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-details-state-card'), {
        propsData: {
            order: orderMock,
            isLoading: false,
            entity: orderMock.transactions.last()
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
                            colorCode: '#94a6b8'
                        }
                    };
                }
            },
            stateMachineService: {
                getState: () => { return { data: { transitions: [] } }; }
            },
            acl: {
                can: () => true
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
                                                lastName: 'Doe'
                                            },
                                            createdAt: new Date()
                                        };
                                    }
                                });
                            }

                            return Promise.resolve(new EntityCollection(
                                '',
                                '',
                                Shopware.Context.api,
                                null,
                                [],
                                0
                            ));
                        }
                    };
                }
            }
        },
        stubs: {
            'sw-order-state-select-v2': true,
            'sw-external-link': { template: '<a href="#"></a>' },
            'sw-order-state-change-modal': true,
            'sw-container': true,
            'sw-card': true,
            'sw-time-ago': true,
            i18n: { template: '<span><slot name="time"></slot><slot name="author"></slot></span>' }
        }
    });
}

describe('src/module/sw-order/component/sw-order-details-state-card', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', {
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {}
            }
        });
    });

    beforeEach(async () => {
        global.repositoryFactoryMock.showError = false;
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show history text', async () => {
        const summary = wrapper.find('.sw-order-detail-state-card__state-history-text');

        expect(summary.exists()).toBeTruthy();
        expect(summary.text()).toEqual('John Doe');
        expect(summary.find('sw-time-ago').exists());
    });
});

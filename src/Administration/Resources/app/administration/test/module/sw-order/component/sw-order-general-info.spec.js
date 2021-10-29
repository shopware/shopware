import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-general-info';

const orderMock = {
    orderNumber: 10000,
    orderCustomer: {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@doe.dev'
    },
    currency: {
        translated: {
            name: ''
        }
    },
    totalRounding: {
        decimals: 2
    },
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: ''
                }
            },
            paymentMethod: {
                translated: {
                    name: ''
                }
            }
        }
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: ''
                }
            },
            shippingMethod: {
                translated: {
                    name: ''
                }
            }
        }
    ],
    stateMachineState: {
        translated: {
            name: ''
        }
    }
};

orderMock.transactions.last = () => ({
    stateMachineState: {
        translated: {
            name: ''
        }
    },
    paymentMethod: {
        translated: {
            name: ''
        }
    }
});

orderMock.deliveries.last = () => ({
    stateMachineState: {
        translated: {
            name: ''
        }
    },
    shippingMethod: {
        translated: {
            name: ''
        }
    }
});

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-general-info'), {
        propsData: {
            order: orderMock,
            isLoading: false
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
                getState: () => { return { data: { } }; }
            },
            feature: {
                isActive: () => true
            }
        },
        stubs: {
            'sw-order-state-select-v2': true,
            'sw-entity-tag-select': true
        }
    });
}

describe('src/module/sw-order/component/sw-order-general-info', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', {
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {}
            },
            mutations: {
                setLoading() {}
            }
        });
    });

    beforeEach(() => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        global.repositoryFactoryMock.showError = false;
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct summary header', () => {
        const summary = wrapper.find('.sw-order-detail-base__general-info__summary-main-header');

        expect(summary.exists()).toBeTruthy();
        expect(summary.text()).toEqual('10000 - John Doe (john@doe.dev)');
    });
});

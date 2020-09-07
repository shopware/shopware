import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/view/sw-order-detail-base';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', v => v);

    const orderMock = {
        shippingCosts: {
            calculatedTaxes: [],
            totalPrice: {}
        },
        currency: {
            translated: {
                shortName: ''
            }
        },
        transactions: [
            {
                stateMachineState: {
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
                shippingCosts: {
                    calculatedTaxes: [],
                    totalPrice: {}
                }
            }
        ],
        stateMachineState: {
            translated: {
                name: ''
            }
        },
        price: {
            calculatedTaxes: []
        }
    };

    orderMock.transactions.last = () => ({
        stateMachineState: {
            translated: {
                name: ''
            }
        }
    });

    return shallowMount(Shopware.Component.build('sw-order-detail-base'), {
        localVue,
        stubs: {
            'sw-card-view': true,
            'sw-order-user-card': true,
            'sw-container': true,
            'sw-order-state-select': true,
            'sw-card': true,
            'sw-order-line-items-grid': true,
            'sw-card-section': true,
            'sw-description-list': true,
            'sw-order-saveable-field': true,
            'sw-order-state-history-card': true,
            'sw-order-delivery-metadata': true,
            'sw-order-document-card': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            orderService: {},
            stateStyleDataProviderService: {
                getStyle: () => ({})
            },
            repositoryFactory: {
                create: (entity) => ({
                    search: () => Promise.resolve([]),
                    get: () => {
                        if (entity === 'order') {
                            return Promise.resolve(orderMock);
                        }

                        return Promise.resolve({});
                    }
                })
            }
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            orderId: '1a2b3c',
            isLoading: false,
            isEditing: false,
            isSaveSuccessful: false
        }
    });
}

describe('src/module/sw-order/view/sw-order-detail-base', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an disabled payment state', () => {
        const paymentState = wrapper.find('.sw-order-state-select__payment-state');

        expect(paymentState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled payment state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);

        const paymentState = wrapper.find('.sw-order-state-select__payment-state');
        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', () => {
        const deliveryState = wrapper.find('.sw-order-state-select__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);

        const deliveryState = wrapper.find('.sw-order-state-select__delivery-state');
        expect(deliveryState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled order state', () => {
        const orderState = wrapper.find('.sw-order-state-select__order-state');

        expect(orderState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled order state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);

        const orderState = wrapper.find('.sw-order-state-select__order-state');
        expect(orderState.attributes().disabled).toBeUndefined();
    });
});

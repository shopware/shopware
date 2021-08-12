import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/view/sw-order-detail-base';

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
        calculatedTaxes: [],
        taxStatus: 'gross'
    },
    totalRounding: {
        interval: 0.01,
        decimals: 2
    },
    itemRounding: {
        interval: 0.01,
        decimals: 2
    },
    lineItems: []
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', Shopware.Filter.getByName('currency'));

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

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled payment state', async () => {
        const paymentState = wrapper.find('.sw-order-state-select__payment-state');

        expect(paymentState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled payment state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);
        await wrapper.vm.$nextTick();

        const paymentState = wrapper.find('.sw-order-state-select__payment-state');
        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', async () => {
        const deliveryState = wrapper.find('.sw-order-state-select__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);
        await wrapper.vm.$nextTick();

        const deliveryState = wrapper.find('.sw-order-state-select__delivery-state');
        expect(deliveryState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled order state', async () => {
        const orderState = wrapper.find('.sw-order-state-select__order-state');

        expect(orderState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled order state', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);
        await wrapper.vm.$nextTick();

        const orderState = wrapper.find('.sw-order-state-select__order-state');
        expect(orderState.attributes().disabled).toBeUndefined();
    });

    it('should display Total excluding VAT and Total including VAT row when tax status is not tax free', async () => {
        await wrapper.destroy();
        wrapper = await createWrapper(['order.editor']);
        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-detail__summary');
        expect(orderSummary.html()).toContain('sw-order.detailBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).toContain('sw-order.detailBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).not.toContain('sw-order.detailBase.summaryLabelAmountGrandTotal');
    });

    it('should only display Total row when tax status tax free', async () => {
        await wrapper.destroy();

        orderMock.price.taxStatus = 'tax-free';
        wrapper = await createWrapper(['order.editor']);
        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-detail__summary');
        expect(orderSummary.html()).not.toContain('sw-order.detailBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).not.toContain('sw-order.detailBase.summaryLabelAmountTotal');
        expect(orderSummary.text()).toContain('sw-order.detailBase.summaryLabelAmount');
    });

    it('should only show 2 decimals number in total orders', async () => {
        orderMock.positionPrice = -0.010000000000218;
        orderMock.amountNet = -0.010000000000218;
        orderMock.currency.translated.shortName = 'EUR';
        orderMock.totalRounding.decimals = 2;

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-detail__summary-data');
        expect(orderSummary.find('dd').text()).toContain('0.01');
    });

    it('should only show 10 decimals number in total orders', async () => {
        orderMock.positionPrice = -0.010000000218;
        orderMock.amountNet = -0.010000000218;
        orderMock.currency.translated.shortName = 'BTC';
        orderMock.totalRounding.decimals = 10;

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-detail__summary-data');

        expect(orderSummary.find('dd').text()).toContain('0.0100000002');
    });
});

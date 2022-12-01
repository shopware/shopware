import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderDetailBase from 'src/module/sw-order/view/sw-order-detail-base';
import swOrderUserCard from 'src/module/sw-order/component/sw-order-user-card';
import 'src/app/component/base/sw-container';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/base/sw-card';

Shopware.Component.register('sw-order-user-card', swOrderUserCard);

const orderMock = {
    billingAddressId: 'wofoo',
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
            },
            shippingMethod: {
                translated: {
                    name: '',
                }
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
    addresses: [
        {
            id: 'wofoo',
        }
    ],
    lineItems: [],
    orderCustomer: {
        firstName: 'Max',
        lastName: 'Mustermann',
        email: 'test@example.com'
    },
    tags: [],
    salesChannel: {
        translated: {
            name: ''
        }
    },
    language: {
        name: '',
    }
};

Shopware.Component.register('sw-order-detail-base', swOrderDetailBase);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', Shopware.Filter.getByName('currency'));

    orderMock.transactions.last = () => ({
        stateMachineState: {
            translated: {
                name: ''
            }
        },
        paymentMethod: {
            translated: {
                distinguishableName: '',
            }
        }
    });

    return shallowMount(await Shopware.Component.build('sw-order-detail-base'), {
        localVue,
        stubs: {
            'sw-card-view': await Shopware.Component.build('sw-card-view'),
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-order-user-card': await Shopware.Component.build('sw-order-user-card'),
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-order-state-select': true,
            'sw-order-line-items-grid': true,
            'sw-card-section': true,
            'sw-description-list': true,
            'sw-order-saveable-field': true,
            'sw-order-state-history-card': true,
            'sw-order-delivery-metadata': true,
            'sw-order-document-card': true,
            'sw-ignore-class': true,
            'sw-extension-component-section': true,
            'sw-avatar': true,
            'sw-order-inline-field': true,
            'sw-address': true,
            'sw-label': true,
            'sw-entity-tag-select': true,
            'sw-button': true,
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
            },
            customSnippetApiService: {
                render: () => Promise.resolve({
                    rendered: {},
                }),
            }
        },
        propsData: {
            orderId: '1a2b3c',
            isLoading: false,
            isEditing: false,
            isSaveSuccessful: false
        },
        mocks: {
            $route: {
                meta: {
                    $module: {
                        color: '#fff',
                    }
                }
            }
        }
    });
}

describe('src/module/sw-order/view/sw-order-detail-base', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
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

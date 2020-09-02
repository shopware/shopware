import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-state-history-card';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const orderProp = {
        transactions: [],
        deliveries: [
            {}
        ]
    };

    orderProp.transactions.last = () => ({});

    return shallowMount(Shopware.Component.build('sw-order-state-history-card'), {
        localVue,
        stubs: {
            'sw-card': '<div><slot></slot></div>',
            'sw-container': true,
            'sw-order-state-card-entry': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            orderService: {},
            stateMachineService: {
                getState: () => ''
            },
            orderStateMachineService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([])
                })
            }
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            title: '',
            order: orderProp
        }
    });
}

describe('src/module/sw-order/component/sw-order-state-history-card', () => {
    let wrapper;

    beforeAll(() => {
        console.warn = () => {};
    });

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
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled payment state', () => {
        wrapper = createWrapper(['order.editor']);
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', () => {
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', () => {
        wrapper = createWrapper(['order.editor']);
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBeUndefined();
    });
});

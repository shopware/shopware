import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-state-history-card';
import 'src/module/sw-order/component/sw-order-state-change-modal';

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
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-container': true,
            'sw-order-state-card-entry': true,
            'sw-order-state-change-modal': Shopware.Component.build('sw-order-state-change-modal'),
            'sw-modal': true,
            'sw-order-state-change-modal-attach-documents': true
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

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled payment state', async () => {
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled payment state', async () => {
        wrapper = createWrapper(['order.editor']);
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', async () => {
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', async () => {
        wrapper = createWrapper(['order.editor']);
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBeUndefined();
    });

    it('should always render order change modal with document selection', async () => {
        wrapper.setData({ showModal: true });

        await wrapper.vm.$nextTick();

        // Order state change modal should always have true mailTemplatesExist prop
        expect(wrapper.find('.sw-order-state-change-modal').props().mailTemplatesExist).toBe(true);

        // Document selection should be visible
        expect(wrapper.find('sw-order-state-change-modal-attach-documents-stub').exists()).toBeTruthy();
    });
});

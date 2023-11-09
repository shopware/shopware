import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */
async function createWrapper() {
    const orderProp = {
        transactions: [],
        deliveries: [
            {},
        ],
    };

    orderProp.transactions.last = () => ({});
    orderProp.transactions.getIds = () => ([]);
    orderProp.deliveries.getIds = () => ([]);

    return mount(await wrapTestComponent('sw-order-state-history-card', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-order-state-history-card-entry': true,
                'sw-order-state-change-modal': await wrapTestComponent('sw-order-state-change-modal', { sync: true }),
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot></div>',
                },
                'sw-order-state-change-modal-attach-documents': true,
            },
            provide: {
                orderService: {},
                stateMachineService: {
                    getState: () => { return { data: { transactions: [] } }; },
                },
                orderStateMachineService: {},
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                    }),
                },
            },
        },
        props: {
            title: '',
            order: orderProp,
        },
    });
}

describe('src/module/sw-order/component/sw-order-state-history-card', () => {
    let wrapper;

    it('should be a Vue.js component', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled payment state', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');
        expect(paymentState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled payment state', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper(['order.editor']);
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBeUndefined();
    });

    it('should always render order change modal with document selection', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({ showModal: true });

        await wrapper.vm.$nextTick();

        // Document selection should be visible
        expect(wrapper.find('sw-order-state-change-modal-attach-documents-stub').exists()).toBeTruthy();
    });
});

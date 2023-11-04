import { shallowMount } from '@vue/test-utils';
import swOrderStateHistoryCard from 'src/module/sw-order/component/sw-order-state-history-card';
import swOrderStateChangeModal from 'src/module/sw-order/component/sw-order-state-change-modal';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-state-history-card', swOrderStateHistoryCard);
Shopware.Component.register('sw-order-state-change-modal', swOrderStateChangeModal);

async function createWrapper(privileges = []) {
    const orderProp = {
        transactions: [],
        deliveries: [
            {},
        ],
    };

    orderProp.transactions.last = () => ({});
    orderProp.transactions.getIds = () => ([]);
    orderProp.deliveries.getIds = () => ([]);

    return shallowMount(await Shopware.Component.build('sw-order-state-history-card'), {
        stubs: {
            'sw-card': {
                template: '<div><slot></slot></div>',
            },
            'sw-container': true,
            'sw-order-state-history-card-entry': true,
            'sw-order-state-change-modal': await Shopware.Component.build('sw-order-state-change-modal'),
            'sw-modal': true,
            'sw-order-state-change-modal-attach-documents': true,
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                },
            },
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
        propsData: {
            title: '',
            order: orderProp,
        },
    });
}

describe('src/module/sw-order/component/sw-order-state-history-card', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
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
        wrapper = await createWrapper(['order.editor']);
        const paymentState = wrapper.find('.sw-order-state-history-card__payment-state');

        expect(paymentState.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled delivery state', async () => {
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBe('true');
    });

    it('should not have an disabled delivery state', async () => {
        wrapper = await createWrapper(['order.editor']);
        const deliveryState = wrapper.find('.sw-order-state-history-card__delivery-state');

        expect(deliveryState.attributes().disabled).toBeUndefined();
    });

    it('should always render order change modal with document selection', async () => {
        await wrapper.setData({ showModal: true });

        await wrapper.vm.$nextTick();

        // Document selection should be visible
        expect(wrapper.find('sw-order-state-change-modal-attach-documents-stub').exists()).toBeTruthy();
    });
});

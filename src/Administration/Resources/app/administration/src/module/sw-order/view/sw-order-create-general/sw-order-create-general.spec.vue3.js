import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-order/mixin/cart-notification.mixin';
import orderStore from 'src/module/sw-order/state/order.store';


/**
 * @package customer-order
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-general', { sync: true }), {
        global: {
            stubs: {
                'sw-card-view': true,
                'sw-card': {
                    template: `
                        <div class="sw-card__content">
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-card-section': await wrapTestComponent('sw-card-section', { sync: true }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'sw-order-line-items-grid-sales-channel': true,
                'sw-extension-component-section': true,
            },
        },
    });
}


describe('src/module/sw-order/view/sw-order-create-general', () => {
    beforeEach(() => {
        if (Shopware.State.get('swOrder')) {
            Shopware.State.unregisterModule('swOrder');
        }

        Shopware.State.registerModule('swOrder', orderStore);
    });

    it('should be show successful notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-not-found': {
                    code: 0,
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 0,
                    message: 'Discount discount has been added',
                    messageKey: 'promotion-discount-added',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();

        wrapper.vm.createNotificationSuccess.mockRestore();
    });

    it('should be show error notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339': {
                    code: 'promotion-code',
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 20,
                    message: 'Promotion with code promotion-code not found!',
                    messageKey: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be show warning notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-warning': {
                    code: 10,
                    key: 'promotion-warning',
                    level: 10,
                    message: 'Promotion with code promotion-code warning!',
                    messageKey: 'promotion-warning',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();

        wrapper.vm.createNotificationWarning.mockRestore();
    });

    it('should only display Total row when status is tax free', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            price: {
                taxStatus: 'tax-free',
            },
        });

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmount');
    });

    it('should display Total excluding VAT and Total including VAT row when tax status is not tax free', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
        });

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountGrandTotal');
    });
});

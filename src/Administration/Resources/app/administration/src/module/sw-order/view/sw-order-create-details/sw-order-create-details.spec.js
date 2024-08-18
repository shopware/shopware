import { mount } from '@vue/test-utils';
import 'src/module/sw-order/mixin/cart-notification.mixin';
import orderStore from 'src/module/sw-order/state/order.store';

/**
 * @package customer-order
 * @group disabledCompat
 */

const contextState = {
    namespaced: true,
    state: { api: { languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b', systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b' } },
    mutations: {
        setLanguageId: jest.fn(),
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-details', { sync: true }), {
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
                'sw-loader': true,
                'sw-order-create-promotion-modal': true,
                'sw-order-customer-address-select': true,
                'sw-entity-single-select': true,
                'sw-container': true,
                'sw-number-field': true,
                'sw-datepicker': true,
                'sw-text-field': true,
                'sw-order-promotion-tag-field': true,
                'sw-switch-field': true,
            },
            provide: {
                cartStoreService: {},
                repositoryFactory: {
                    create: () => ({
                        get: () => Promise.resolve(),
                    }),
                },
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-create-details', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', orderStore);
        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
        });

        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', contextState);
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

    it('should be set context language when language selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            context: {
                languageId: null,
            },
        });

        expect(contextState.mutations.setLanguageId).not.toHaveBeenCalled();

        await wrapper.setData({
            context: {
                languageId: '1234',
            },
        });

        expect(contextState.mutations.setLanguageId).toHaveBeenCalledWith(expect.anything(), '1234');
    });
});

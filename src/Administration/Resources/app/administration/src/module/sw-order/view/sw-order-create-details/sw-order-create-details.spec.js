import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/mixin/cart-notification.mixin';
import swOrderCreateDetails from 'src/module/sw-order/view/sw-order-create-details';
import Vuex from 'vuex';
import orderStore from 'src/module/sw-order/state/order.store';

/**
 * @package checkout
 */

Shopware.Component.register('sw-order-create-details', swOrderCreateDetails);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('tooltip', {});
    localVue.filter('currency', v => v);
    return shallowMount(await Shopware.Component.build('sw-order-create-details'), {
        localVue,
        stubs: {
            'sw-card-view': true,
            'sw-card': {
                template: `
                    <div class="sw-card__content">
                        <slot name="grid"></slot>
                    </div>
                `,
            },
        },
        provide: {
            cartStoreService: {},
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve(),
                }),
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
});

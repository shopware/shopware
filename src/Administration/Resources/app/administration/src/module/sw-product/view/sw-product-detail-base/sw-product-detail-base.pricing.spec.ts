/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import { shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';

const routerPush = jest.fn();

async function createWrapper() {
    return shallowMount(await wrapTestComponent('sw-product-detail-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div><slot name="headerRight" /></div>',
                },
            },
            mocks: {
                $router: { push: routerPush },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve({ first: () => ({ folder: {} }) }),
                    }),
                },
            },
        },
    });
}

describe('sw-product-detail-base pricing', () => {
    let wrapper: VueWrapper;

    beforeEach(() => {
        routerPush.mockClear();
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = {
            id: 'productId',
            _isNew: false,
        };
        store.creationType = 'physical';
        store.modeSettings = ['prices'];
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should show advanced pricing only for saved products and navigate on click', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-product-detail-base__advanced-prices').trigger('click');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.product.detail.prices',
            params: { id: 'productId' },
        });

        Shopware.Store.get('swProductDetail').product._isNew = true;
        await nextTick();
        expect(wrapper.find('.sw-product-detail-base__advanced-prices').exists()).toBe(false);
    });
});

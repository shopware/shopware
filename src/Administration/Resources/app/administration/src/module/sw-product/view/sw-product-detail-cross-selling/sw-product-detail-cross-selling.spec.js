/**
 * @package inventory
 */

import { mount } from '@vue/test-utils';

import productStore from 'src/module/sw-product/page/sw-product-detail/state';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-detail-cross-selling', { sync: true }), {
        props: {
            crossSelling: null,
        },
        global: {
            stubs: {
                'sw-card': true,
                'sw-button': true,
                'sw-product-cross-selling-form': true,
                'sw-empty-state': true,
                'sw-skeleton': true,
                'sw-icon': true,
                'sw-inheritance-switch': true,
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve('bar') }),
                },
                acl: { can: () => true },
            },
        },
    });
}

function buildProduct() {
    return {
        crossSellings: [
            {
                assignedProducts: [
                    'bar',
                ],
            },
        ],
    };
}

describe('src/module/sw-product/view/sw-product-detail-cross-selling', () => {
    let wrapper;

    beforeEach(async () => {
        if (Shopware.State.get('swProductDetail')) {
            Shopware.State.unregisterModule('swProductDetail');
        }
        Shopware.State.registerModule('swProductDetail', productStore);

        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }
        Shopware.State.registerModule('context', {
            namespaced: true,

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                },
            },

            state: {
                api: {
                    assetsPath: '/',
                },
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load assigned products', async () => {
        const customProduct = buildProduct();

        wrapper = await createWrapper();
        await wrapper.setData({ product: customProduct });
        await flushPromises();

        expect(customProduct.crossSellings[0].assignedProducts).toStrictEqual(['bar']);
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: 'parentProductId',
            crossSellings: [],
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main product', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            crossSellings: [],
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(false);
        expect(wrapper.vm.isInherited).toBe(false);
    });
});

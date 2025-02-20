/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-detail-cross-selling', {
            sync: true,
        }),
        {
            props: {
                crossSelling: null,
            },
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-product-cross-selling-form': true,
                    'sw-empty-state': true,
                    'sw-skeleton': true,
                    'sw-icon': true,
                    'sw-inheritance-switch': true,
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'router-link': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve('bar'),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
        },
    );
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
        Shopware.Store.get('swProductDetail').$reset();

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }
        Shopware.Store.register({
            id: 'context',

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                },
            },

            state() {
                return {
                    api: {
                        assetsPath: '/',
                    },
                };
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

        expect(customProduct.crossSellings[0].assignedProducts).toStrictEqual([
            'bar',
        ]);
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: 'parentProductId',
            crossSellings: [],
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main product', async () => {
        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: null,
            crossSellings: [],
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(false);
        expect(wrapper.vm.isInherited).toBe(false);
    });
});

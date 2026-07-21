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
                    'sw-product-cross-selling-form': true,
                    'mt-card': {
                        props: [
                            'title',
                        ],
                        template: '<div class="mt-card" :data-title="title"><slot></slot></div>',
                    },
                    'mt-empty-state': {
                        template: '<div class="mt-empty-state"><slot name="button"></slot></div>',
                    },
                    'mt-switch': {
                        props: [
                            'ariaLabel',
                        ],
                        template: '<input class="mt-switch" :aria-label="ariaLabel" type="checkbox">',
                    },
                    'sw-skeleton': true,
                    'sw-inheritance-switch': true,

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
        expect(wrapper.find('.mt-switch').attributes('aria-label')).toBe('sw-product.crossselling.inheritSwitchLabel');
        expect(wrapper.find('label.sw-product-detail-cross-selling__inheritance-label').exists()).toBe(false);
        expect(wrapper.find('span.sw-product-detail-cross-selling__inheritance-label').exists()).toBe(true);
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
        expect(wrapper.find('.mt-card').attributes('data-title')).toBe('sw-product.crossselling.cardTitleCrossSelling');
        expect(wrapper.find('.mt-empty-state').attributes('headline')).toBe('sw-product.crossselling.emptyStateTitle');
        expect(wrapper.find('.mt-empty-state').attributes('description')).toBe(
            'sw-product.crossselling.emptyStateDescription',
        );
    });
});

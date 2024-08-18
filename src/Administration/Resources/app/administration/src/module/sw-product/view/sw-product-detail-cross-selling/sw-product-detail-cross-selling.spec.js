/*
 * @package inventory
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

const product = {};
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
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve('bar') }),
                },
                acl: { can: () => true },
            },
            mocks: {
                $store: createStore({
                    modules: {
                        swProductDetail: {
                            namespaced: true,
                            getters: {
                                isLoading: () => false,
                            },
                            state: {
                                product: product,
                            },
                        },
                        context: {
                            namespaced: true,

                            getters: {
                                isSystemDefaultLanguage() {
                                    return true;
                                },
                            },
                        },
                    },
                }),
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
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should load assigned products', async () => {
        const customProduct = buildProduct();

        await wrapper.setData({ product: customProduct });
        await flushPromises();

        expect(customProduct.crossSellings[0].assignedProducts).toStrictEqual(['bar']);
    });
});

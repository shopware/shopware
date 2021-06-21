import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/view/sw-product-detail-cross-selling';
import Vuex from 'vuex';

const product = {};
const store = new Vuex.Store({
    modules: {
        swProductDetail: {
            namespaced: true,
            getters: {
                isLoading: () => false
            },
            state: {
                product: product
            }
        },
        context: {
            namespaced: true,

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                }
            }
        }
    }
});

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.filter('asset', () => {});
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-product-detail-cross-selling'), {
        localVue,
        propsData: {
            crossSelling: null
        },
        stubs: {
            'sw-card': true,
            'sw-button': true,
            'sw-product-cross-selling-form': true
        },
        mocks: {
            $store: store
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve('bar') })
            },
            acl: { can: () => true }
        }
    });
}

function buildProduct() {
    return {
        crossSellings: [
            {
                assignedProducts: [
                ]
            }
        ]
    };
}

describe('src/module/sw-product/view/sw-product-detail-cross-selling', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should load assigned products', async () => {
        const customProduct = buildProduct();
        await wrapper.setData({ product: customProduct });

        expect(customProduct.crossSellings[0].assignedProducts).toBe('bar');
    });
});

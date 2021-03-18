import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-seo';

const { Component, State } = Shopware;

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-detail-seo'), {
        localVue,
        mocks: {
            $t: key => key,
            $tc: key => key,
            $store: State._store
        },
        provide: {
            feature: {
                isActive: () => true
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-card': true,
            'sw-product-seo-form': true,
            'sw-seo-url': true,
            'sw-seo-main-category': true
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-seo', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {}
            },
            getters: {
                isLoading: () => false
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should update product main categories correctly', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            product: {
                mainCategories: []
            }
        });

        await wrapper.vm.onAddMainCategory({
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0'
        });

        expect(wrapper.vm.product.mainCategories).toEqual(expect.arrayContaining([{
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0'
        }]));
    });
});

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-layout';

const { Component, State } = Shopware;

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-detail-layout'), {
        localVue,
        mocks: {
            $t: key => key,
            $tc: key => key,
            $router: { push: () => {} },
            $store: State._store
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: (id) => {
                        if (!id) {
                            return Promise.resolve(null);
                        }
                        return Promise.resolve({ id });
                    }
                })
            },
            feature: {
                isActive: () => true
            }
        },
        stubs: {
            'sw-card': true,
            'sw-product-layout-assignment': true,
            'sw-cms-layout-modal': true
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-layout', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: null
            },
            mutations: {
                setProduct(state, product) {
                    state.product = product;
                }
            },
            getters: {
                isLoading: () => false
            }
        });
        State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentPage: null
            },
            mutations: {
                setCurrentPage(state, currentPage) {
                    state.currentPage = currentPage;
                }
            }
        });
    });

    it('should turn on layout modal', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            showLayoutModal: true
        });

        const layoutModal = wrapper.find('sw-cms-layout-modal-stub');

        expect(layoutModal.exists()).toBeTruthy();
    });

    it('should turn off layout modal', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            showLayoutModal: false
        });

        const layoutModal = wrapper.find('sw-cms-layout-modal-stub');

        expect(layoutModal.exists()).toBeFalsy();
    });

    it('should redirect to cms creation page', async () => {
        const wrapper = createWrapper();

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$store.commit('cmsPageState/setCurrentPage', null);

        await wrapper.vm.onOpenInPageBuilder();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.cms.create' });
        wrapper.vm.$router.push.mockRestore();
    });

    it('should redirect to cms detail page', async () => {
        const wrapper = createWrapper();

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$store.commit('cmsPageState/setCurrentPage', { id: 'id' });

        await wrapper.vm.onOpenInPageBuilder();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.cms.detail', params: { id: 'id' } });
        wrapper.vm.$router.push.mockRestore();
    });

    it('should be able to select a product page layout', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onSelectLayout('cmsPageId');

        expect(wrapper.vm.currentPage).toEqual({ id: 'cmsPageId' });
    });

    it('should be able to reset a product page layout', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onResetLayout();

        expect(wrapper.vm.currentPage).toEqual(null);
    });
});

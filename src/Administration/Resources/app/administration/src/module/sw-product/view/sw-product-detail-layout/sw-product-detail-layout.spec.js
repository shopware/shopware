/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDetailLayout from 'src/module/sw-product/view/sw-product-detail-layout';

Shopware.Component.register('sw-product-detail-layout', swProductDetailLayout);

const { State } = Shopware;

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-product-detail-layout'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: (id) => {
                        if (!id) {
                            return Promise.resolve(null);
                        }
                        return Promise.resolve({
                            id,
                            sections: [{
                                blocks: [{
                                    slots: [{
                                        id: 'slot1',
                                        config: {
                                            content: {
                                                value: 'product.name',
                                                source: 'mapped',
                                            },
                                        },
                                    }],
                                }],
                            }],
                        });
                    },
                }),
            },
            cmsService: {
                getEntityMappingTypes: () => {},
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        stubs: {
            'sw-card': {
                template: '<div><slot></slot></div>',
            },
            'sw-product-layout-assignment': true,
            'sw-cms-layout-modal': true,
            'sw-cms-page-form': true,
            'sw-skeleton': true,
        },
    });
}


describe('src/module/sw-product/view/sw-product-detail-layout', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: null,
            },
            mutations: {
                setProduct(state, product) {
                    state.product = product;
                },
            },
            getters: {
                isLoading: () => false,
            },
        });
        State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentPage: null,
            },
            mutations: {
                setCurrentPage(state, currentPage) {
                    state.currentPage = currentPage;
                },

                removeCurrentPage(state) {
                    state.currentPage = null;
                },

                setCurrentMappingEntity(state, entity) {
                    state.currentMappingEntity = entity;
                },

                removeCurrentMappingEntity(state) {
                    state.currentMappingEntity = null;
                },

                setCurrentMappingTypes(state, types) {
                    state.currentMappingTypes = types;
                },

                removeCurrentMappingTypes(state) {
                    state.currentMappingTypes = {};
                },

                setCurrentDemoEntity(state, entity) {
                    state.currentDemoEntity = entity;
                },

                removeCurrentDemoEntity(state) {
                    state.currentDemoEntity = null;
                },
            },

            actions: {
                resetCmsPageState({ commit }) {
                    commit('removeCurrentPage');
                    commit('removeCurrentMappingEntity');
                    commit('removeCurrentMappingTypes');
                    commit('removeCurrentDemoEntity');
                },
            },
        });
    });

    it('should turn on layout modal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showLayoutModal: true,
        });

        const layoutModal = wrapper.find('sw-cms-layout-modal-stub');

        expect(layoutModal.exists()).toBeTruthy();
    });

    it('should turn off layout modal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showLayoutModal: false,
        });

        const layoutModal = wrapper.find('sw-cms-layout-modal-stub');

        expect(layoutModal.exists()).toBeFalsy();
    });

    it('should redirect to cms creation page', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$store.commit('cmsPageState/setCurrentPage', null);

        await wrapper.vm.onOpenInPageBuilder();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.cms.create' });
        wrapper.vm.$router.push.mockRestore();
    });

    it('should redirect to cms detail page', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$store.commit('cmsPageState/setCurrentPage', { id: 'id' });

        await wrapper.vm.onOpenInPageBuilder();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.cms.detail', params: { id: 'id' } });
        wrapper.vm.$router.push.mockRestore();
    });

    it('should be able to select a product page layout', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$store.commit('swProductDetail/setProduct', { id: '1' });

        wrapper.vm.onSelectLayout('cmsPageId');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.product.cmsPageId).toBe('cmsPageId');
        expect(wrapper.vm.currentPage.id).toBe('cmsPageId');
    });

    it('should be able to reset a product page layout', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onResetLayout();

        expect(wrapper.vm.product.cmsPageId).toBeNull();
    });

    it('should be able to overwrite product config to selected layout config', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: '1',
            cmsPageId: 'cmsPageId',
            slotConfig: {
                slot1: {
                    content: {
                        value: 'Hello World',
                        source: 'static',
                    },
                },
            },
        });

        const wrapper = await createWrapper();
        await wrapper.vm.handleGetCmsPage();

        expect(wrapper.vm.currentPage.sections[0].blocks[0].slots[0].config).toEqual({
            content: {
                value: 'Hello World',
                source: 'static',
            },
        });
    });

    it('onOpenLayoutModal: should be able to open layout assignment', async () => {
        const wrapper = await createWrapper(['product.editor']);
        wrapper.vm.onOpenLayoutModal();

        expect(wrapper.vm.showLayoutModal).toBeTruthy();
    });

    it('onOpenLayoutModal: should not be able to open layout assignment', async () => {
        const wrapper = await createWrapper(['product.viewer']);
        wrapper.vm.onOpenLayoutModal();

        expect(wrapper.vm.showLayoutModal).toBeFalsy();
    });

    it('should not be able to view layout config', async () => {
        const wrapper = await createWrapper(['product.viewer']);
        const cmsForm = wrapper.find('sw-cms-page-form-stub');
        const infoNoConfig = wrapper.find('.sw-product-detail-layout__no-config');

        expect(cmsForm.exists()).toBeFalsy();
        expect(infoNoConfig.exists()).toBeFalsy();
    });

    it('should be able to view layout config', async () => {
        const wrapper = await createWrapper(['product.editor']);
        const cmsForm = wrapper.find('sw-cms-page-form-stub');
        const infoNoConfig = wrapper.find('.sw-product-detail-layout__no-config');

        expect(cmsForm.exists()).toBeTruthy();
        expect(infoNoConfig.exists()).toBeFalsy();
    });

    it('should not be able to view layout config if cms page is locked', async () => {
        Shopware.State.commit('cmsPageState/setCurrentPage', { id: 'id', locked: true });

        const wrapper = await createWrapper(['product.editor']);
        const cmsForm = wrapper.find('sw-cms-page-form-stub');
        const infoNoConfig = wrapper.find('.sw-product-detail-layout__no-config');

        expect(cmsForm.exists()).toBeFalsy();
        expect(infoNoConfig.exists()).toBeTruthy();
    });
});

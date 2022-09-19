import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-category/page/sw-category-detail';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-category-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="side-content"></slot>
    </div>`
            },
            'sw-category-tree': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-sidebar-collapse': true,
            'sw-landing-page-tree': true
        },
        provide: {
            cmsService: {},
            repositoryFactory: {},
            seoUrlService: {}
        },
    });
}

describe('src/module/sw-category/page/sw-category-detail', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            actions: {
                resetCmsPageState: () => {}
            }
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable the save button', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', { category: {} });

        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBe('true');
    });

    it('should enable the save button', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });


        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should not allow to edit', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-edit']).toBeUndefined();
    });

    it('should allow to edit', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-edit']).toBe('true');
    });

    it('should not allow to create', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-create']).toBeUndefined();
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['category.creator'];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-create']).toBe('true');
    });

    it('should not allow to delete', async () => {
        global.activeAclRoles = [];

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-delete']).toBeUndefined();
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = ['category.deleter'];

        const wrapper = createWrapper();
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-delete']).toBe('true');
    });

    it('should navigate when forceDiscardChanges is true', async () => {
        const wrapper = createWrapper();
        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: ''
            }
        });

        await wrapper.setData({
            forceDiscardChanges: true,
            isLoading: false
        });

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, null, null, next);
        expect(next).toHaveBeenCalledWith();
    });

    it('should navigate when no category is selected', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoading: false
        });

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, null, null, next);
        expect(next).toHaveBeenCalledWith();
    });

    it('should navigate to `sw.cms.create` when just `cmsPageId` is changed to `null`', async () => {
        const mockCategoryId = 'MOCK_CATEGORY_ID';

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                id: mockCategoryId,
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        wrapper.vm.changesetGenerator.generate = (category) => {
            expect(category.id).toBe(mockCategoryId);
            return {
                changes: {
                    id: 'MOCK_CMS_PAGE_ID',
                    versionId: 'MOCK_CMS_PAGE_VERSION',
                    cmsPageId: null
                },
                deletionQueue: []
            };
        };

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, { name: 'sw.cms.create' }, null, next);
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(false);
        expect(wrapper.vm.nextRoute).toBe(null);
        expect(next).toHaveBeenCalledWith();
    });

    it('should navigate when nothing is changed', async () => {
        const nextRouteMock = { name: 'sw.category.index' };
        const mockCategoryId = 'MOCK_CATEGORY_ID';

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                id: mockCategoryId,
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        wrapper.vm.changesetGenerator.generate = (category) => {
            expect(category.id).toBe(mockCategoryId);
            return {
                changes: {
                    id: 'MOCK_CMS_PAGE_ID',
                    versionId: 'MOCK_CMS_PAGE_VERSION',
                },
                deletionQueue: []
            };
        };

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, nextRouteMock, null, next);
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(false);
        expect(wrapper.vm.nextRoute).toBe(null);
        expect(next).toHaveBeenCalledWith();
    });

    it('should not navigate when anything else is changed', async () => {
        const nextRouteMock = { name: 'sw.category.index' };
        const mockCategoryId = 'MOCK_CATEGORY_ID';

        const wrapper = createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                id: mockCategoryId,
                slotConfig: ''
            }
        });

        await wrapper.setData({
            isLoading: false
        });

        wrapper.vm.changesetGenerator.generate = (category) => {
            expect(category.id).toBe(mockCategoryId);
            return {
                changes: {
                    id: 'MOCK_CMS_PAGE_ID',
                    versionId: 'MOCK_CMS_PAGE_VERSION',
                    products: 'PRODUCT_COLLECTION_MOCK'
                },
                deletionQueue: []
            };
        };

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, nextRouteMock, null, next);
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(true);
        expect(wrapper.vm.nextRoute).toStrictEqual(nextRouteMock);
        expect(next).toHaveBeenCalledWith(false);
    });
});

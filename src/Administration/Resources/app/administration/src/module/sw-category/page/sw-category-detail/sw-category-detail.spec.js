/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCategoryDetail from 'src/module/sw-category/page/sw-category-detail';
import 'src/app/component/sidebar/sw-sidebar-collapse';
import 'src/app/component/base/sw-collapse';

Shopware.Component.register('sw-category-detail', swCategoryDetail);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-category-detail'), {
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="side-content"></slot>
    </div>`,
            },
            'sw-category-tree': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-sidebar-collapse': await Shopware.Component.build('sw-sidebar-collapse'),
            'sw-collapse': await Shopware.Component.build('sw-collapse'),
            'sw-landing-page-tree': true,
            'sw-icon': true,
        },
        provide: {
            cmsService: {
                getEntityMappingTypes: () => {},
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve({
                        get: () => ({ sections: [] }),
                    }),
                }),
            },
            seoUrlService: {},
        },
    });
}

describe('src/module/sw-category/page/sw-category-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];

        if (Shopware.State.get('cmsPageState')) {
            Shopware.State.unregisterModule('cmsPageState');
        }

        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            actions: {
                resetCmsPageState: () => {},
            },
            mutations: {
                setCurrentMappingEntity: () => {},
                setCurrentMappingTypes: () => {},
                setCurrentDemoEntity: () => {},
                setCurrentPage: () => {},
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable the save button', async () => {
        const wrapper = await createWrapper();
        Shopware.State.commit('swCategoryDetail/setActiveCategory', { category: {} });
        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBe('true');
    });

    it('should enable the save button', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', { category: {
            slotConfig: '',
        } });

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-category-detail__save-action');

        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should not allow to edit', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-edit']).toBeUndefined();
    });

    it('should allow to edit', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-edit']).toBe('true');
    });

    it('should not allow to create', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-create']).toBeUndefined();
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['category.creator'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-create']).toBe('true');
    });

    it('should not allow to delete', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-delete']).toBeUndefined();
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = ['category.deleter'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const categoryTree = wrapper.find('sw-category-tree-stub');

        expect(categoryTree.attributes()['allow-delete']).toBe('true');
    });
});

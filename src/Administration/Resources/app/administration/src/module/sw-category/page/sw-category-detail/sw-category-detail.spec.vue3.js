/**
 * @package content
 */
import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                    <div>
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                        <slot name="side-content"></slot>
                    </div>`,
                },
                'sw-category-tree': {
                    template: '<div class="sw-category-tree"></div>',
                    props: ['allowEdit', 'allowCreate', 'allowDelete'],
                },
                'sw-button': true,
                'sw-button-process': {
                    template: '<div class="sw-button-process"><slot></slot></div>',
                    props: ['disabled'],
                },
                'sw-sidebar-collapse': {
                    template: `
                    <div class="sw-sidebar-collapse">
                        <slot name="header"></slot>
                        <slot name="actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                },
                'sw-collapse': await wrapTestComponent('sw-collapse'),
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

    it('should not allow to modify', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(true);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(false);
        expect(categoryTree.props('allowDelete')).toBe(false);
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

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor', 'category.deleter'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
            },
        });

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(true);
    });
});

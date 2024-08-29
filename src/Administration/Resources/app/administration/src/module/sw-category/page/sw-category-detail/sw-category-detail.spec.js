/**
 * @package inventory
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

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
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-skeleton': true,
                'sw-category-view': true,
                'sw-category-entry-point-overwrite-modal': true,
                'sw-landing-page-view': true,
                'sw-discard-changes-modal': true,
                'sw-empty-state': true,
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
                        save: jest.fn(() => Promise.resolve()),
                        get: () => Promise.resolve({
                            slotConfig: '',
                            navigationSalesChannels: [],
                            footerSalesChannels: [],
                            serviceSalesChannels: [],
                        }),
                    }),
                },
                seoUrlService: {},
                systemConfigApiService: {
                    getValues: () => Promise.resolve({
                        'core.cms.default_category_cms_page': 'foo',
                    }),
                },
            },
        },
    });
}

describe('src/module/sw-category/page/sw-category-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];

        Shopware.Store.unregister('cmsPageState');
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                currentPage: null,
            }),
            actions: {
                resetCmsPageState: () => {},
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

    it('should set default layout', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor', 'category.deleter'];

        const wrapper = await createWrapper();

        Shopware.State.commit('swCategoryDetail/setActiveCategory', {
            category: {
                slotConfig: '',
                cmsPageId: 'foo',
                navigationSalesChannels: [],
                footerSalesChannels: [],
                serviceSalesChannels: [],
            },
        });

        await wrapper.setData({
            isLoading: false,
            cmsPage: null,

        });

        await wrapper.setProps({
            categoryId: 'foo',
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.categoryRepository.save.mock.calls[0][0].cmsPageId).toBeNull();
    });
});

/**
 * @package inventory
 */
import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import swCategoryState from 'src/module/sw-category/page/sw-category-detail/state';

async function createWrapper() {
    const routes = [
        {
            name: 'sw.category.detail',
            path: '/category/detail/:id',
        },
    ];

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(await wrapTestComponent('sw-category-tree', { sync: true }), {
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'sw-loader': true,
                'sw-skeleton': true,
                'sw-tree': {
                    props: ['items'],
                    template: `
                        <div class="sw-tree">
                          <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                        </div>
                    `,
                },
                'sw-tree-item': true,
            },
            provide: {
                syncService: {},
                repositoryFactory: {
                    create: () => ({
                        search: () =>
                            Promise.resolve([
                                {
                                    id: '1a',
                                },
                            ]),
                        delete: () => Promise.resolve(),
                        get: () => Promise.resolve(),
                        saveAll: () => Promise.resolve(),
                        syncDeleted: () => Promise.resolve(),
                    }),
                },
            },
        },
        props: {
            currentLanguageId: '1a2b3c',
        },
    });
}

describe('src/module/sw-category/component/sw-category-tree', () => {
    beforeAll(() => {
        if (Shopware.State.get('swCategoryDetail')) {
            Shopware.State.unregisterModule('swCategoryDetail');
        }

        Shopware.State.registerModule('swCategoryDetail', swCategoryState);
    });

    it('should be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBeDefined();
    });

    it('should not be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        expect(wrapper.vm.sortable).toBe(false);
    });

    it('should be able to delete the items in sw-tree', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete the items in sw-tree', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should be able to create new categories in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeDefined();
    });

    it('should not be able to create new categories in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowCreate: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete categories in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete categories in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeDefined();
    });

    it('should not show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeUndefined();
    });

    it('should show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right category url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).toBe('#/category/detail/1a2b');
    });

    it('should get wrong category url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).not.toBe('#/detail/1a2b');
    });

    [
        { serviceSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7a' }] },
        {
            navigationSalesChannels: [
                { id: '4d9ef75adbb149aa99785a0a969b3b7b' },
            ],
        },
        { footerSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7c' }] },
    ].forEach((entryPoint) => {
        it(`should not be able to delete a category having ${Object.keys(entryPoint)[0]} as initial entry point`, async () => {
            const wrapper = await createWrapper();
            wrapper.vm.createNotificationError = jest.fn();

            await wrapper.setData({
                isLoadingInitialData: false,
            });

            const category = {
                id: '1a',
                isNew: () => false,
                parentId: 'parent',
                ...entryPoint,
            };

            await wrapper.vm.onDeleteCategory({ data: category, children: [] });

            const notificationMock = wrapper.vm.createNotificationError;

            expect(notificationMock).toHaveBeenCalledTimes(1);
            expect(notificationMock).toHaveBeenCalledWith({
                message: 'sw-category.general.errorNavigationEntryPoint',
            });

            wrapper.vm.createNotificationError.mockRestore();
        });
    });

    it('should not be able to delete a category having serviceSalesChannels as initial entry point', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const entryPoint = {
            serviceSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7a' }],
        };
        const category = {
            id: '1a',
            isNew: () => false,
            parentId: 'parent',
            ...entryPoint,
        };

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        const notificationMock = wrapper.vm.createNotificationError;

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-category.general.errorNavigationEntryPoint',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be able to delete a category having an empty entry point', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const category = {
            id: '1a',
            isNew: () => false,
        };

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        const notificationMock = wrapper.vm.createNotificationError;

        expect(notificationMock).toHaveBeenCalledTimes(0);
        expect(wrapper.emitted()['category-checked-elements-count']).toBeUndefined();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be able to set elements count when delete category is checked', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });
        wrapper.vm.$refs.categoryTree.checkedElementsCount = 2;

        const category = {
            id: '1a',
            isNew: () => false,
        };

        await wrapper.vm.onDeleteCategory({
            data: category,
            children: [],
            checked: true,
        });

        const emitted = wrapper.emitted()['category-checked-elements-count'];

        expect(emitted).toBeTruthy();
        expect(emitted).toEqual([[1]]);
        expect(wrapper.vm.$refs.categoryTree.checkedElementsCount).toBe(1);
    });

    it('should fix the sorting right after deleting a single category', async () => {
        const wrapper = await createWrapper();

        const category = {
            id: '2',
            isNew: () => false,
            parentId: '1',
            afterCategoryId: '1',
        };

        await wrapper.setData({
            loadedCategories: {
                1: { id: '1', parentId: '1', afterCategoryId: null },
                2: { id: '2', parentId: '1', afterCategoryId: '1' },
                // The `afterCategoryId` is "1" here, because in the actual code it was already fixed before
                // `onDeleteCategory` is executed, see `sw-tree`::deleteElement()
                3: { id: '3', parentId: '1', afterCategoryId: '1' },
                4: { id: '4', parentId: '1', afterCategoryId: '3' },
            },
        });

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        expect(wrapper.vm.loadedCategories[3].afterCategoryId).toBe('1');
    });

    it('should fix the sorting right after deleting multiple categories', async () => {
        const wrapper = await createWrapper();

        const categories = {
            2: {},
            4: {},
            5: {},
        };

        await wrapper.setData({
            loadedCategories: {
                1: {
                    id: '1',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: null,
                },
                2: {
                    id: '2',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: '1',
                },
                3: {
                    id: '3',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: '2',
                },
                4: {
                    id: '4',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: '3',
                },
                5: {
                    id: '5',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: '4',
                },
                6: {
                    id: '6',
                    parentId: '1',
                    navigationSalesChannels: null,
                    afterCategoryId: '5',
                },
            },
        });

        await wrapper.vm.deleteCheckedItems(categories);

        expect(wrapper.vm.loadedCategories[3].afterCategoryId).toBe('1');
        expect(wrapper.vm.loadedCategories[6].afterCategoryId).toBe('3');
    });
});

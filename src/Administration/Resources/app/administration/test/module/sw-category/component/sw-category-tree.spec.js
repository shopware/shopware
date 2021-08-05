import { config, createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-tree';
import VueRouter from 'vue-router';
import swCategoryState from 'src/module/sw-category/page/sw-category-detail/state';

function createWrapper() {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const routes = [{
        name: 'sw.category.detail',
        path: 'category/detail/:id'
    }];

    const router = new VueRouter({
        routes
    });

    return shallowMount(Shopware.Component.build('sw-category-tree'), {
        localVue,
        router,
        stubs: {
            'sw-loader': true,
            'sw-tree': {
                props: ['items'],
                template: `
                    <div class="sw-tree">
                      <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                    </div>
                `
            },
            'sw-tree-item': true
        },
        provide: {
            syncService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([
                        {
                            id: '1a'
                        }
                    ]),
                    delete: () => Promise.resolve(),
                    get: () => Promise.resolve()
                })
            }
        },
        propsData: {
            currentLanguageId: '1a2b3c'
        }
    });
}

describe('src/module/sw-category/component/sw-category-tree', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swCategoryDetail', swCategoryState);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to sort the items', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBeDefined();
    });

    it('should not be able to sort the items', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowEdit: false
        });

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBeUndefined();
    });

    it('should be able to delete the items in sw-tree', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete the items in sw-tree', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowDelete: false
        });

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should be able to create new categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeDefined();
    });

    it('should not be able to create new categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowCreate: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowDelete: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeDefined();
    });

    it('should not show the checkbox in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowEdit: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeUndefined();
    });

    it('should show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.setProps({
            allowEdit: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right category url', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).toEqual('#category/detail/1a2b');
    });

    it('should get wrong category url', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).not.toEqual('#/detail/1a2b');
    });

    [
        { serviceSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7a' }] },
        { navigationSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7b' }] },
        { footerSalesChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7c' }] }

    ].forEach(entryPoint => {
        it(`should not be able to delete a category having ${Object.keys(entryPoint)[0]} as initial entry point`, async () => {
            const wrapper = createWrapper();
            wrapper.vm.createNotificationError = jest.fn();

            await wrapper.setData({
                isLoadingInitialData: false
            });

            const category = {
                id: '1a',
                isNew: () => false,
                ...entryPoint
            };

            await wrapper.vm.onDeleteCategory({ data: category, children: [] });

            const notificationMock = wrapper.vm.createNotificationError;

            expect(notificationMock).toBeCalledTimes(1);
            expect(notificationMock).toHaveBeenCalledWith({
                message: 'sw-category.general.errorNavigationEntryPoint'
            });

            wrapper.vm.createNotificationError.mockRestore();
        });
    });

    it('should be able to delete a category having an empty entry point', async () => {
        const wrapper = createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        const category = {
            id: '1a',
            isNew: () => false
        };

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        const notificationMock = wrapper.vm.createNotificationError;

        expect(notificationMock).toHaveBeenCalledTimes(0);

        wrapper.vm.createNotificationError.mockRestore();
    });
});

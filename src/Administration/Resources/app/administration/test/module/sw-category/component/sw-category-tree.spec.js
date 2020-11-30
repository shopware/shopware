import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-tree';
import VueRouter from 'vue-router';

function createWrapper() {
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
        mocks: {
            $tc: v => v
        },
        provide: {
            syncService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([
                        {
                            id: '1a'
                        }
                    ])
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
        Shopware.State.registerModule('swCategoryDetail', {});
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
        expect(tree.attributes().allowdeletecategories).toBeDefined();
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
        expect(tree.attributes().allowdeletecategories).toBeUndefined();
    });

    it('should be able to create new categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes().allownewcategories).toBeDefined();
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
        expect(treeItem.attributes().allownewcategories).toBeUndefined();
    });

    it('should be able to delete categories in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes().allowdeletecategories).toBeDefined();
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
        expect(treeItem.attributes().allowdeletecategories).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes().displaycheckbox).toBeDefined();
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
        expect(treeItem.attributes().displaycheckbox).toBeUndefined();
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
        expect(treeItem.attributes().contextmenutooltiptext).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes().contextmenutooltiptext).toBeUndefined();
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
});

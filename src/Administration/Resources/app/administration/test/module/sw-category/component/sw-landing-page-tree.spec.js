import { config, createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-landing-page-tree';
import VueRouter from 'vue-router';
import swCategoryState from 'src/module/sw-category/page/sw-category-detail/state';

function createWrapper(privileges = ['landing_page.creator', 'landing_page.editor']) {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const routes = [{
        name: 'sw.category.landingPage',
        path: 'category/landingPage/:id'
    }];

    const router = new VueRouter({
        routes
    });

    return shallowMount(Shopware.Component.build('sw-landing-page-tree'), {
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
            'sw-tree-item': true,
            'sw-button': true
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
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            currentLanguageId: '1a2b3c'
        }
    });
}

describe('src/module/sw-category/component/sw-landing-page-tree', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swCategoryDetail', swCategoryState);
    });

    let oldSystemLanguageId = null;
    beforeEach(async () => {
        // this is normally set by the shopware runtime
        // but needed for this unit tests because the component relies on this value.
        oldSystemLanguageId = Shopware.Context.api.systemLanguageId;
        Shopware.Context.api.systemLanguageId = '1a2b3c';
    });

    afterEach(async () => {
        Shopware.Context.api.systemLanguageId = oldSystemLanguageId;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
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

    it('should be able to create new landing pages', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-landing-page-tree__add-button-button');
        expect(treeItem.attributes().disabled).toBeUndefined();
    });

    it('should not be able to create new landing pages in sw-tree-item', async () => {
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

    it('should be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('sw-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete landing pages in sw-tree-item', async () => {
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

    it('should get right landing page url', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).toEqual('#category/landingPage/1a2b');
    });

    it('should get wrong landing page url', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).not.toEqual('#/landingPage/1a2b');
    });
});

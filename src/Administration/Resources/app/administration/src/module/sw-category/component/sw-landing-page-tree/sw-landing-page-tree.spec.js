/**
 * @package content
 */
import { config, createLocalVue, shallowMount } from '@vue/test-utils';
import swLandingPageTree from 'src/module/sw-category/component/sw-landing-page-tree';
import VueRouter from 'vue-router';
import swCategoryState from 'src/module/sw-category/page/sw-category-detail/state';

Shopware.Component.register('sw-landing-page-tree', swLandingPageTree);

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const routes = [{
        name: 'sw.category.landingPageDetail',
        path: 'category/landingPage/:id',
    }];

    const router = new VueRouter({
        routes,
    });

    return shallowMount(await Shopware.Component.build('sw-landing-page-tree'), {
        localVue,
        router,
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
            'sw-tree-item': {
                props: ['item'],
                template: `
                    <div class="sw-tree-item">
                      <slot name="actions" :toolTip="{ delay: 300, message: 'jest', active: true}"></slot>
                    </div>
                `,
            },
            'sw-button': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
        },
        provide: {
            syncService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([
                        {
                            id: '1a',
                        },
                    ]),
                }),
            },
        },
        propsData: {
            currentLanguageId: '1a2b3c',
        },
    });
}

describe('src/module/sw-category/component/sw-landing-page-tree', () => {
    let oldSystemLanguageId = null;
    beforeEach(async () => {
        global.activeAclRoles = ['landing_page.creator', 'landing_page.editor'];

        if (Shopware.State.get('swCategoryDetail')) {
            Shopware.State.unregisterModule('swCategoryDetail');
        }

        Shopware.State.registerModule('swCategoryDetail', swCategoryState);

        // this is normally set by the shopware runtime
        // but needed for this unit tests because the component relies on this value.
        oldSystemLanguageId = Shopware.Context.api.systemLanguageId;
        Shopware.Context.api.systemLanguageId = '1a2b3c';
    });

    afterEach(async () => {
        Shopware.Context.api.systemLanguageId = oldSystemLanguageId;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBeUndefined();
    });

    it('should be able to delete the items in sw-tree', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should be able to create new landing pages', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-landing-page-tree__add-button-button');
        expect(treeItem.attributes().disabled).toBeUndefined();
    });

    it('should not be able to create new landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowCreate: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
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

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
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

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.vm.$nextTick();

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).toBe('#category/landingPage/1a2b');
    });

    it('should get wrong landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });
        await wrapper.vm.$nextTick();

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).not.toBe('#/landingPage/1a2b');
    });
});

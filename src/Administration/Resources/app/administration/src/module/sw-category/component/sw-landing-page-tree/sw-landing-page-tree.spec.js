/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

function createLandingPages(count, offset = 0) {
    return Array.from({ length: count }, (_, index) => ({ id: `id-${offset + index}` }));
}

function createSearchResult(items, total) {
    const result = [...items];
    result.total = total;

    return result;
}

async function createWrapper(search = () => Promise.resolve([{ id: '1a' }])) {
    const routes = [
        {
            name: 'sw.category.landingPageDetail',
            path: '/category/landingPage/:id',
        },
    ];

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(await wrapTestComponent('sw-landing-page-tree', { sync: true }), {
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
                'sw-tree-item': {
                    props: ['item'],
                    template: `
                        <div class="sw-tree-item">
                            <slot name="actions" :toolTip="{ delay: 300, message: 'jest', active: true}"></slot>
                        </div>
                    `,
                },
                'sw-context-button': true,
                'sw-context-menu-item': true,
            },
            provide: {
                syncService: {},
                repositoryFactory: {
                    create: () => ({
                        search,
                    }),
                },
            },
        },
        props: {
            currentLanguageId: '1a2b3c',
        },
    });
}

describe('src/module/sw-category/component/sw-landing-page-tree', () => {
    let oldSystemLanguageId = null;
    beforeEach(async () => {
        global.activeAclRoles = [
            'landing_page.creator',
            'landing_page.editor',
        ];

        Shopware.Store.get('swCategoryDetail').$reset();

        // this is normally set by the shopware runtime
        // but needed for this unit tests because the component relies on this value.
        oldSystemLanguageId = Shopware.Context.api.systemLanguageId;
        Shopware.Context.api.systemLanguageId = '1a2b3c';
    });

    afterEach(async () => {
        Shopware.Context.api.systemLanguageId = oldSystemLanguageId;
    });

    it('should not be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBe('false');
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

    it('should be able to create new landing pages', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

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

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

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

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

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

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).toBe('#/category/landingPage/1a2b');
    });

    it('should get wrong landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).not.toBe('#/landingPage/1a2b');
    });

    it('should request the first page with a stable sorting', async () => {
        const search = jest.fn(() => Promise.resolve(createSearchResult([], 0)));

        await createWrapper(search);
        await flushPromises();

        const criteria = search.mock.calls[0][0];

        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(500);
        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'name' }),
            expect.objectContaining({ field: 'id' }),
        ]);
    });

    it('should offer loading more landing pages when the list is truncated', async () => {
        const search = () => Promise.resolve(createSearchResult(createLandingPages(500), 700));

        const wrapper = await createWrapper(search);
        await flushPromises();

        expect(wrapper.vm.hasMoreLandingPages).toBe(true);
        expect(wrapper.find('.sw-landing-page-tree__load-more-button').exists()).toBe(true);
    });

    it('should not offer loading more landing pages when everything is loaded', async () => {
        const search = () => Promise.resolve(createSearchResult(createLandingPages(12), 12));

        const wrapper = await createWrapper(search);
        await flushPromises();

        expect(wrapper.vm.hasMoreLandingPages).toBe(false);
        expect(wrapper.find('.sw-landing-page-tree__load-more-button').exists()).toBe(false);
    });

    it('should append the next page and keep the already loaded landing pages', async () => {
        const search = jest.fn((criteria) =>
            Promise.resolve(createSearchResult(createLandingPages(500, (criteria.page - 1) * 500), 700)),
        );

        const wrapper = await createWrapper(search);
        await flushPromises();

        expect(wrapper.vm.landingPages).toHaveLength(500);

        await wrapper.find('.sw-landing-page-tree__load-more-button').trigger('click');
        await flushPromises();

        expect(search.mock.calls[1][0].page).toBe(2);
        expect(wrapper.vm.landingPages).toHaveLength(1000);
        expect(wrapper.vm.landingPages[0].id).toBe('id-0');
    });

    it('should keep the current page when loading more landing pages fails', async () => {
        const search = jest
            .fn()
            .mockResolvedValueOnce(createSearchResult(createLandingPages(500), 700))
            .mockRejectedValueOnce(new Error('failed'));

        const wrapper = await createWrapper(search);
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find('.sw-landing-page-tree__load-more-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.isLoadingMore).toBe(false);
        expect(wrapper.vm.landingPages).toHaveLength(500);
    });

    it('should reset paging when the language changes', async () => {
        const search = jest.fn(() => Promise.resolve(createSearchResult(createLandingPages(500), 700)));

        const wrapper = await createWrapper(search);
        await flushPromises();

        await wrapper.find('.sw-landing-page-tree__load-more-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);

        await wrapper.setProps({ currentLanguageId: 'other-language-id' });
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.landingPages).toHaveLength(500);
    });

    it('should keep the total in sync when a landing page is removed', async () => {
        const search = () => Promise.resolve(createSearchResult(createLandingPages(500), 700));

        const wrapper = await createWrapper(search);
        await flushPromises();

        wrapper.vm.removeFromStore('id-0');

        expect(wrapper.vm.total).toBe(699);
        expect(wrapper.vm.landingPages).toHaveLength(499);
    });
});

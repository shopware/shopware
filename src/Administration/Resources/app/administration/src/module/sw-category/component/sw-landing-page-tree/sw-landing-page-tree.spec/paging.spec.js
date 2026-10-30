/**
 * @sw-package discovery
 */

import {
    createLandingPages,
    createSearchResult,
    createWrapper,
    setupLandingPageTreeHooks,
} from './sw-landing-page-tree.fixtures';

describe('src/module/sw-category/component/sw-landing-page-tree', () => {
    setupLandingPageTreeHooks();

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

    it('should not request another page while one is still loading', async () => {
        let resolveSecondPage;
        const search = jest
            .fn()
            .mockResolvedValueOnce(createSearchResult(createLandingPages(500), 700))
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveSecondPage = resolve;
                }),
            );

        const wrapper = await createWrapper(search);
        await flushPromises();

        const button = wrapper.getComponent('.sw-landing-page-tree__load-more-button');

        await button.trigger('click');
        expect(button.props('disabled')).toBe(true);

        await button.trigger('click');

        expect(search).toHaveBeenCalledTimes(2);

        resolveSecondPage(createSearchResult(createLandingPages(200, 500), 700));
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.isLoadingMore).toBe(false);
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

    it('should drop a deleted landing page from the tree', async () => {
        const search = () => Promise.resolve(createSearchResult(createLandingPages(500), 700));

        const wrapper = await createWrapper(search);
        await flushPromises();

        wrapper.vm.removeFromStore('id-0');

        expect(wrapper.vm.landingPages).toHaveLength(499);
    });

    it('should reload the loaded pages after a delete so shifted entries stay reachable', async () => {
        // 501 landing pages: page one is full, one entry is left over.
        let serverCount = 501;
        const search = jest.fn((criteria) => {
            const offset = (criteria.page - 1) * 500;
            const size = Math.max(0, Math.min(500, serverCount - offset));

            return Promise.resolve(createSearchResult(createLandingPages(size, offset), serverCount));
        });

        const wrapper = await createWrapper(search);
        await flushPromises();

        expect(wrapper.vm.landingPages).toHaveLength(500);
        expect(wrapper.vm.hasMoreLandingPages).toBe(true);

        const deleteMock = jest.fn(() => {
            serverCount -= 1;

            return Promise.resolve();
        });
        wrapper.vm.landingPageRepository.delete = deleteMock;

        await wrapper.vm.onDeleteLandingPage({ data: { id: 'id-0', isNew: () => false } });
        await flushPromises();

        // Without the reload the offset cursor would still sit behind the shrunken result set,
        // so the entry that moved into page one would never be fetched.
        expect(wrapper.vm.total).toBe(500);
        expect(wrapper.vm.landingPages).toHaveLength(500);
        expect(wrapper.vm.hasMoreLandingPages).toBe(false);
    });

    it('should never expose an empty tree while reloading', async () => {
        let resolveReload;
        const search = jest
            .fn()
            .mockResolvedValueOnce(createSearchResult(createLandingPages(500), 500))
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveReload = resolve;
                }),
            );

        const wrapper = await createWrapper(search);
        await flushPromises();

        const reload = wrapper.vm.reloadLandingPages();
        await flushPromises();

        expect(wrapper.vm.landingPages).toHaveLength(500);

        resolveReload(createSearchResult(createLandingPages(499), 499));
        await reload;

        expect(wrapper.vm.landingPages).toHaveLength(499);
    });

    it('should reload every page that was loaded before the mutation', async () => {
        const search = jest.fn((criteria) =>
            Promise.resolve(createSearchResult(createLandingPages(500, (criteria.page - 1) * 500), 1000)),
        );

        const wrapper = await createWrapper(search);
        await flushPromises();

        await wrapper.find('.sw-landing-page-tree__load-more-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        search.mockClear();

        await wrapper.vm.reloadLandingPages();

        expect(search.mock.calls.map(([criteria]) => criteria.page)).toEqual([1, 2]);
        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.landingPages).toHaveLength(1000);
    });
});

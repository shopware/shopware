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

    it('should duplicate a landing page as an inactive top-level copy', async () => {
        const serverLandingPages = createLandingPages(2);
        const search = jest.fn((criteria) => {
            if (criteria.ids.length > 0) {
                return Promise.resolve([{ id: 'clone-id', childCount: 3, parentId: 'id-0' }]);
            }

            return Promise.resolve(createSearchResult(serverLandingPages, serverLandingPages.length));
        });

        const wrapper = await createWrapper(search);
        await flushPromises();

        const cloneMock = jest.fn(() => {
            serverLandingPages.push({ id: 'clone-id' });

            return Promise.resolve({ id: 'clone-id' });
        });
        wrapper.vm.landingPageRepository.clone = cloneMock;

        wrapper.vm.duplicateElement({ id: 'id-0', data: { name: 'Summer sale', url: 'summer-sale' } });
        await flushPromises();

        expect(cloneMock).toHaveBeenCalledWith(
            'id-0',
            {
                cloneChildren: false,
                overwrites: {
                    name: 'Summer sale global.default.copy',
                    url: 'summer-sale-global.default.copy',
                    active: false,
                },
            },
            Shopware.Context.api,
        );
        expect(search.mock.lastCall[0].ids).toEqual(['clone-id']);

        const clones = wrapper.vm.landingPages.filter(({ id }) => id === 'clone-id');
        expect(clones).toEqual([{ id: 'clone-id', childCount: 0, parentId: null }]);
        expect(wrapper.vm.landingPages).toHaveLength(3);
    });

    it('should show an error notification when duplicating a landing page fails', async () => {
        const search = jest.fn(() => Promise.resolve(createSearchResult(createLandingPages(2), 2)));

        const wrapper = await createWrapper(search);
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.landingPageRepository.clone = jest.fn(() =>
            Promise.reject({
                response: { status: 400, data: { errors: [{ code: 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION' }] } },
            }),
        );
        search.mockClear();

        wrapper.vm.duplicateElement({ id: 'id-0', data: { name: 'Summer sale', url: 'summer-sale' } });
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.notification.unspecifiedSaveErrorMessage',
        });
        expect(search).not.toHaveBeenCalled();
        expect(wrapper.vm.landingPages).toHaveLength(2);
    });
});

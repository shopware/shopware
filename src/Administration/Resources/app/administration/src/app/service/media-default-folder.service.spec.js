/**
 * @sw-package discovery
 */
import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';
import SwMediaModalFolderSettings from 'src/app/component/media/sw-media-modal-folder-settings';

const { Criteria } = Shopware.Data;

describe('app/service/media-default-folder.service.js', () => {
    it('should be a function', async () => {
        const type = typeof MediaDefaultFolderService;
        expect(type).toBe('function');
    });

    it('should return a getDefaultFolderId function', async () => {
        const mediaDefaultFolderService = MediaDefaultFolderService();
        expect(mediaDefaultFolderService.hasOwnProperty('getDefaultFolderId')).toBe(true);
    });

    it('getDefaultFolderId should search the id of the folder assigned to the entity', async () => {
        const searchIds = jest.fn(() => Promise.resolve({ total: 0, data: ['defaultFolderId'] }));
        const create = jest.fn(() => ({ searchIds }));
        Shopware.Service('repositoryFactory').create = create;

        const id = await MediaDefaultFolderService().getDefaultFolderId('product');

        expect(id).toBe('defaultFolderId');
        expect(create).toHaveBeenCalledWith('media_folder');

        const [criteria, cacheOptions] = searchIds.mock.calls[0];
        expect(criteria).toBeInstanceOf(Criteria);
        expect(criteria.getLimit()).toBe(1);
        expect(criteria.totalCountMode).toBe(0);
        expect(criteria.filters).toEqual([
            expect.objectContaining({
                field: 'defaultFolder.entity',
                type: 'equals',
                value: 'product',
            }),
        ]);
        expect(cacheOptions).toEqual({
            cacheKey: ['media-default-folder', 'product', 'folder-id'],
        });
    });

    it('getDefaultFolderId should return null when the entity has no default folder', async () => {
        Shopware.Service('repositoryFactory').create = () => ({
            searchIds: () => Promise.resolve({ total: 0, data: [] }),
        });

        expect(await MediaDefaultFolderService().getDefaultFolderId('product')).toBeNull();
    });

    it('getDefaultFolderId should return null when the lookup fails', async () => {
        Shopware.Service('repositoryFactory').create = () => ({
            searchIds: () => Promise.reject(new Error('Missing privilege')),
        });

        expect(await MediaDefaultFolderService().getDefaultFolderId('product')).toBeNull();
    });

    it('getDefaultFolderId should reload after the folder settings modal invalidated its cache', async () => {
        const cacheService = Shopware.Service('cacheService');
        cacheService.clear();

        const load = jest
            .fn()
            .mockResolvedValueOnce({ total: 0, data: ['old-folder-id'] })
            .mockResolvedValueOnce({ total: 0, data: ['new-folder-id'] });
        Shopware.Service('repositoryFactory').create = () => ({
            searchIds: (criteria, { cacheKey }) => cacheService.query({ key: cacheKey, fn: load }),
        });
        const service = MediaDefaultFolderService();

        expect(await service.getDefaultFolderId('product')).toBe('old-folder-id');
        expect(await service.getDefaultFolderId('product')).toBe('old-folder-id');

        await SwMediaModalFolderSettings.methods.invalidateMediaDefaultFolderCache();

        expect(await service.getDefaultFolderId('product')).toBe('new-folder-id');
        expect(load).toHaveBeenCalledTimes(2);
    });
});

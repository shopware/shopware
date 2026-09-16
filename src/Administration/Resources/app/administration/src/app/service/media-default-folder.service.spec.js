/**
 * @sw-package discovery
 */
import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';

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

    it('getDefaultFolderId should use criteria with a correct association and filter', async () => {
        const factory = Shopware.Service('repositoryFactory');
        factory.create = () => {
            return {
                search: (criteria, cacheOptions) => {
                    expect(criteria).toEqual(
                        expect.objectContaining({
                            associations: expect.arrayContaining([
                                expect.objectContaining({
                                    association: 'folder',
                                }),
                            ]),
                            filters: expect.arrayContaining([
                                expect.objectContaining({
                                    field: 'entity',
                                    type: 'equals',
                                    value: 'product',
                                }),
                            ]),
                        }),
                    );
                    expect(cacheOptions).toEqual({
                        cacheKey: [
                            'media-default-folder',
                            'product',
                        ],
                    });

                    return Promise.resolve({
                        first: () => {
                            return { folder: { id: 'defaultFolderId' } };
                        },
                    });
                },
            };
        };

        const mediaDefaultFolderService = MediaDefaultFolderService();

        const id = await mediaDefaultFolderService.getDefaultFolderId('product');
        expect(id).toBe('defaultFolderId');
    });

    it('getDefaultFolderId should pass an entity-scoped cache key to the repository', async () => {
        const search = jest.fn(() =>
            Promise.resolve({
                first: () => {
                    return { folder: { id: 'defaultFolderId' } };
                },
            }),
        );

        Shopware.Service('repositoryFactory').create = () => {
            return { search };
        };

        const mediaDefaultFolderService = MediaDefaultFolderService();

        await mediaDefaultFolderService.getDefaultFolderId('product');

        expect(search).toHaveBeenCalledWith(expect.any(Criteria), {
            cacheKey: [
                'media-default-folder',
                'product',
            ],
        });
    });
});

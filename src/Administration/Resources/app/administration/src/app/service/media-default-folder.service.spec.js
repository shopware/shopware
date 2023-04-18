/**
 * @package content
 */
import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';

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
                search: (criteria, context) => {
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
                    expect(context).toEqual(Shopware.Context.api);

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

    it('getDefaultFolderId function should return a response faster when called with the same argument', async () => {
        const mediaDefaultFolderService = MediaDefaultFolderService();

        const startNotSaved = performance.now();
        mediaDefaultFolderService.getDefaultFolderId('product');
        const endNotSaved = performance.now();

        const startSaved = performance.now();
        mediaDefaultFolderService.getDefaultFolderId('product');
        const endSaved = performance.now();

        const savedResponse = endSaved - startSaved;
        const notSavedResponse = endNotSaved - startNotSaved;

        expect(savedResponse).toBeLessThan(notSavedResponse);
    });
});

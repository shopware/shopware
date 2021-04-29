import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';

describe('app/service/media-default-folder.service.js', () => {
    it('should be a function', () => {
        const type = typeof MediaDefaultFolderService;
        expect(type).toEqual('function');
    });

    it('should return a getDefaultFolderId function', () => {
        const mediaDefaultFolderService = MediaDefaultFolderService();
        expect(mediaDefaultFolderService.hasOwnProperty('getDefaultFolderId')).toBe(true);
    });

    it('getDefaultFolderId should use criteria with a correct association and filter', () => {
        const factory = Shopware.Service('repositoryFactory');
        factory.create = () => {
            return {
                search: (criteria, context) => {
                    expect(criteria).toEqual(
                        expect.objectContaining({
                            associations: expect.arrayContaining([
                                expect.objectContaining({
                                    association: 'folder'
                                })
                            ]),
                            filters: expect.arrayContaining([
                                expect.objectContaining({
                                    field: 'entity',
                                    type: 'equals',
                                    value: 'product'
                                })
                            ])
                        })
                    );
                    expect(context).toEqual(Shopware.Context.api);

                    return Promise.resolve({
                        first: () => {
                            return { folder: { id: 'defaultFolderId' } };
                        }
                    });
                }
            };
        };

        const mediaDefaultFolderService = MediaDefaultFolderService();

        mediaDefaultFolderService.getDefaultFolderId('product').then((id) => {
            expect(id).toEqual('defaultFolderId');
        });
    });
});

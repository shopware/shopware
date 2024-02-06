/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-media-modal-folder-settings', { sync: true }), {
        props: {
            mediaFolderId: '12345',
            disabled: false,
        },
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal', { sync: true }),
                'sw-tabs': await wrapTestComponent('sw-tabs', { sync: true }),
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        return {
                            create: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve([]);
                            },
                            searchIds: () => {
                                return Promise.resolve([]);
                            },
                            get: () => {
                                switch (entity) {
                                    case 'media_folder_configuration':
                                        return Promise.resolve({
                                            mediaThumbnailSizes: {
                                                entity: 'media_thumbnail_size',
                                                source: 'media_thumbnail_size',
                                            },
                                        });
                                    default:
                                        return Promise.resolve({
                                            id: '12345',
                                            name: 'Test folder',
                                            parentId: null,
                                            configurationId: '12345',
                                        });
                                }
                            },
                        };
                    },
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
        },
    });
}

describe('src/app/asyncComponent/media/sw-media-modal-folder-settings', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get thumbnail sizes and unused thumbnail sizes with the correct criteria', async () => {
        const searchIds = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'searchIds');
        const search = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'search');

        const getUnusedThumbnailSizes = jest.spyOn(wrapper.vm, 'getUnusedThumbnailSizes');
        const getThumbnailSizes = jest.spyOn(wrapper.vm, 'getThumbnailSizes');

        await wrapper.vm.createdComponent();

        expect(getUnusedThumbnailSizes).toHaveBeenCalled();
        expect(getThumbnailSizes).toHaveBeenCalled();

        expect(searchIds).toHaveBeenCalledWith(expect.objectContaining({
            filters: [
                {
                    field: 'mediaFolderConfigurations.mediaFolders.id',
                    type: 'equals',
                    value: null,
                },
            ],
        }));
        expect(search).toHaveBeenCalledWith(expect.objectContaining({
            sortings: [
                {
                    field: 'width',
                    naturalSorting: false,
                    order: 'ASC',
                },
            ],
        }));
    });

    it('should update thumbnail sizes correctly', async () => {
        wrapper.vm.mediaThumbnailSizeRepository.searchIds = jest.fn(() => {
            return Promise.resolve({
                data: ['12345'],
            });
        });
        wrapper.vm.mediaThumbnailSizeRepository.search = jest.fn(() => {
            return Promise.resolve([
                {
                    id: '12345',
                    width: 100,
                    height: 100,
                },
                {
                    id: '67890',
                    width: 200,
                    height: 200,
                },
            ]);
        });

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.unusedThumbnailSizes).toEqual(['12345']);
        expect(wrapper.vm.thumbnailSizes).toEqual([
            {
                id: '12345',
                width: 100,
                height: 100,
                deletable: true,
            },
            {
                id: '67890',
                width: 200,
                height: 200,
                deletable: false,
            },
        ]);
    });
});

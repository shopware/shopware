/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';
import componentConfig from 'src/app/asyncComponent/media/sw-media-modal-folder-settings';

Shopware.Component.register('sw-media-modal-folder-settings', componentConfig);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-media-modal-folder-settings'), {
        propsData: {
            mediaFolderId: '12345',
            disabled: false,
        },
        stubs: {
            'sw-modal': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
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
                        save: () => {
                            return Promise.resolve();
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

    it('should be able to add a new thumbnail size', async () => {
        const create = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'create').mockImplementation(() => {
            return { _isNew: true };
        });
        const save = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'save').mockImplementation(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],

        });
        await wrapper.vm.addThumbnail({
            width: 30,
            height: 30,
        });

        expect(create).toHaveBeenCalled();
        expect(save).toHaveBeenCalledWith(
            expect.objectContaining({
                _isNew: true,
                width: 30,
                height: 30,
            }),
            expect.any(Object),
        );
    });

    it('should not be able to add a new thumbnail size if the size already exists', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],

        });
        await wrapper.vm.addThumbnail({
            width: 10,
            height: 10,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-modal-folder-settings.notification.error.messageThumbnailSizeExisted',
        });
    });
});

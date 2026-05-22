/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const MEDIA_LIBRARY_PREFERENCES_KEY = 'media.library.preferences';

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    async function createWrapper({
        props = {},
        userConfigService = {
            search: jest.fn().mockResolvedValue({ data: {} }),
            upsert: jest.fn().mockResolvedValue(),
        },
        mediaFolderRepository = {
            get: jest.fn().mockResolvedValue({ id: 'folder-id' }),
        },
        mediaRepository = {
            get: jest.fn().mockResolvedValue({
                id: 'media-id',
                getEntityName: () => 'media',
            }),
        },
    } = {}) {
        return mount(await wrapTestComponent('sw-media-modal-v2', { sync: true }), {
            props: {
                uploadTag: 'my-upload',
                ...props,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'mt-modal': true,
                    'mt-modal-root': true,
                    'sw-tabs': {
                        template: '<div><slot name="content" active="upload"></slot></div>',
                    },
                    'sw-media-sidebar': true,
                    'sw-media-upload-v2': true,
                    'sw-upload-listener': true,
                    'sw-media-grid': true,
                    'sw-tabs-item': true,
                    'sw-media-breadcrumbs': true,
                    'sw-simple-search-field': true,
                    'sw-media-library': true,
                    'sw-media-media-item': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (repositoryName) => {
                            if (repositoryName === 'media_folder') {
                                return mediaFolderRepository;
                            }

                            if (repositoryName === 'media') {
                                return mediaRepository;
                            }

                            return {};
                        },
                    },
                    mediaService: {},
                    userConfigService,
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });

    it('should show the library drop upload mask for file drags', async () => {
        const dropUpload = wrapper.find('.sw-media-modal-v2__drop-upload');

        expect(dropUpload.exists()).toBe(true);
        expect(dropUpload.attributes().variant).toBe('regular');

        wrapper.vm.onFileDragEnter({
            dataTransfer: {
                types: ['Files'],
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-modal-v2__drop-upload').classes()).toContain('is--active');

        wrapper.vm.onFileDrop();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-modal-v2__drop-upload').classes()).not.toContain('is--active');
    });

    it('should ignore non-file drags for the library drop upload mask', async () => {
        wrapper.vm.onFileDragEnter({
            dataTransfer: {
                types: ['text/plain'],
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-modal-v2__drop-upload').classes()).not.toContain('is--active');
    });

    it('should restore the remembered media modal folder', async () => {
        const mediaFolderRepository = {
            get: jest.fn().mockResolvedValue({ id: 'remembered-folder-id' }),
        };

        wrapper = await createWrapper({
            userConfigService: {
                search: jest.fn().mockResolvedValue({
                    data: {
                        [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                            lastFolderId: 'remembered-folder-id',
                        },
                    },
                }),
                upsert: jest.fn().mockResolvedValue(),
            },
            mediaFolderRepository,
        });
        await flushPromises();

        expect(wrapper.vm.folderId).toBe('remembered-folder-id');
        expect(mediaFolderRepository.get).toHaveBeenCalledWith('remembered-folder-id', expect.any(Object));
    });

    it('should prefer the initial folder over the remembered media modal folder', async () => {
        wrapper = await createWrapper({
            props: {
                initialFolderId: 'initial-folder-id',
            },
            userConfigService: {
                search: jest.fn().mockResolvedValue({
                    data: {
                        [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                            lastFolderId: 'remembered-folder-id',
                        },
                    },
                }),
                upsert: jest.fn().mockResolvedValue(),
            },
        });
        await flushPromises();

        expect(wrapper.vm.folderId).toBe('initial-folder-id');
    });

    it('should save the last media modal folder', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({ data: {} }),
            upsert: jest.fn().mockResolvedValue(),
        };

        wrapper = await createWrapper({ userConfigService });
        await flushPromises();

        userConfigService.upsert.mockClear();
        wrapper.vm.folderId = 'next-folder-id';
        await flushPromises();

        expect(userConfigService.upsert).toHaveBeenCalledWith({
            [MEDIA_LIBRARY_PREFERENCES_KEY]: {
                lastFolderId: 'next-folder-id',
            },
        });
    });

    it('should select uploaded media after refreshing the media library', async () => {
        const uploadedMedia = {
            id: 'uploaded-media-id',
            getEntityName: () => 'media',
        };
        wrapper = await createWrapper({
            mediaRepository: {
                get: jest.fn().mockResolvedValue(uploadedMedia),
            },
        });

        wrapper.vm.$refs.mediaLibrary.refreshList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem = jest.fn();

        await wrapper.vm.onUploadFinished({ targetId: uploadedMedia.id });

        expect(wrapper.vm.$refs.mediaLibrary.refreshList).toHaveBeenCalled();
        expect(wrapper.vm.selection).toEqual([uploadedMedia]);
        expect(wrapper.vm.uploads).toEqual([uploadedMedia]);
        expect(wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem).toHaveBeenCalledWith(uploadedMedia);
    });

    it('should check uploaded media by entity id instead of object reference', async () => {
        const uploadedMedia = {
            id: 'uploaded-media-id',
            getEntityName: () => 'media',
        };
        const selectedMedia = {
            id: 'uploaded-media-id',
            getEntityName: () => 'media',
        };

        wrapper.vm.selection = [selectedMedia];

        expect(wrapper.vm.checkMediaItem(uploadedMedia)).toBe(true);
    });

    it('should remove selected media by entity id instead of object reference', async () => {
        const selectedMedia = {
            id: 'uploaded-media-id',
            getEntityName: () => 'media',
        };

        wrapper.vm.selection = [selectedMedia];

        wrapper.vm.onMediaRemoveSelected({
            item: {
                id: 'uploaded-media-id',
                getEntityName: () => 'media',
            },
        });

        expect(wrapper.vm.selection).toEqual([]);
    });
});

/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ mediaRepositoryGet = () => Promise.resolve() } = {}) {
    return mount(await wrapTestComponent('sw-media-index', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-search-bar': true,
                'sw-media-sidebar': true,
                'sw-upload-listener': true,
                'sw-language-switch': true,
                'router-link': true,
                'sw-media-upload-v2': true,
                'sw-media-library': true,
                'sw-loader': true,
            },
            mocks: {
                $route: {
                    query: '',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        get: mediaRepositoryGet,
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                mediaService: {},
            },
        },
    });
}
describe('src/module/sw-media/page/sw-media-index', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should contain the default accept value', async () => {
        const wrapper = await createWrapper();
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('*/*');
    });

    it('should contain "application/pdf" value', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });

    it('should not be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.viewer'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');
        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should render the media drop upload for file drags', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const dropUpload = wrapper.find('.sw-media-index__media-drop-upload');
        expect(dropUpload.exists()).toBe(true);
        expect(dropUpload.attributes().variant).toBe('regular');

        wrapper.vm.onFileDragEnter({
            dataTransfer: {
                types: ['Files'],
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-index__media-drop-upload').classes()).toContain('is--active');

        wrapper.vm.onFileDrop();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-index__media-drop-upload').classes()).not.toContain('is--active');
    });

    it('should ignore non-file drags for the media drop upload', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();

        wrapper.vm.onFileDragEnter({
            dataTransfer: {
                types: ['text/plain'],
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-media-index__media-drop-upload').classes()).not.toContain('is--active');
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('refreshes the list when the last upload finishes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('refreshes the list when the last upload fails', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        wrapper.vm.onUploadFailed({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('does not refresh the list before all uploads are finished', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 2;

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).not.toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
        expect(wrapper.vm.pendingUploadsCount).toBe(1);
    });

    it('selects successfully uploaded media after the list was refreshed', async () => {
        const uploadedMedia = {
            id: 'upload-id',
        };
        const wrapper = await createWrapper({
            mediaRepositoryGet: jest.fn().mockResolvedValue(uploadedMedia),
        });

        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [uploadedMedia];
        wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem = jest.fn();

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.selectedItems).toEqual([uploadedMedia]);
        expect(wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem).toHaveBeenCalledWith(uploadedMedia);
        expect(wrapper.vm.successfulUploads).toEqual([]);
    });

    it('does not select successful uploads before the upload batch is complete', async () => {
        const uploadedMedia = {
            id: 'upload-id',
        };
        const wrapper = await createWrapper({
            mediaRepositoryGet: jest.fn().mockResolvedValue(uploadedMedia),
        });

        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 2;
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [uploadedMedia];

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).not.toHaveBeenCalled();
        expect(wrapper.vm.selectedItems).toEqual([]);
        expect(wrapper.vm.successfulUploads).toEqual([uploadedMedia]);
    });

    it('only selects uploaded media that is visible in the refreshed list', async () => {
        const uploadedMedia = {
            id: 'upload-id',
            mediaFolderId: null,
        };
        const wrapper = await createWrapper({
            mediaRepositoryGet: jest.fn().mockResolvedValue(uploadedMedia),
        });

        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [];
        wrapper.vm.$refs.mediaLibrary.normalizedTypeFilter = ['image'];

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.selectedItems).toEqual([]);
        expect(wrapper.vm.successfulUploads).toEqual([]);
    });

    it('selects successfully uploaded media from the current folder when it is not in the refreshed first page', async () => {
        const uploadedMedia = {
            id: 'upload-id',
            mediaFolderId: 'folder-id',
        };
        const wrapper = await createWrapper({
            mediaRepositoryGet: jest.fn().mockResolvedValue(uploadedMedia),
        });
        await wrapper.setProps({
            routeFolderId: 'folder-id',
        });

        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [];
        wrapper.vm.$refs.mediaLibrary.normalizedTypeFilter = [];

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.selectedItems).toEqual([uploadedMedia]);
        expect(wrapper.vm.successfulUploads).toEqual([]);
    });

    it('ignores successful upload selection when the uploaded media can not be resolved', async () => {
        const wrapper = await createWrapper({
            mediaRepositoryGet: jest.fn().mockRejectedValue(new Error('not found')),
        });

        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [{ id: 'upload-id' }];

        await wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.selectedItems).toEqual([]);
        expect(wrapper.vm.successfulUploads).toEqual([]);
    });

    it('keeps the selected media when the language changes', async () => {
        const selectedMedia = {
            id: 'media-id',
            getEntityName: () => 'media',
        };
        const translatedMedia = {
            id: 'media-id',
            getEntityName: () => 'media',
            translated: {
                title: 'Translated title',
            },
        };
        const wrapper = await createWrapper();

        wrapper.vm.selectedItems = [selectedMedia];
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [translatedMedia];
        wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem = jest.fn();

        await wrapper.vm.onChangeLanguage();

        expect(wrapper.vm.reloadList).toHaveBeenCalled();
        expect(wrapper.vm.selectedItems).toEqual([translatedMedia]);
        expect(wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem).toHaveBeenCalledWith(translatedMedia);
    });

    it('resolves the selected item again when it is not visible after a language change', async () => {
        const selectedMedia = {
            id: 'media-id',
            getEntityName: () => 'media',
        };
        const translatedMedia = {
            id: 'media-id',
            getEntityName: () => 'media',
        };
        const mediaRepositoryGet = jest.fn().mockResolvedValue(translatedMedia);
        const wrapper = await createWrapper({
            mediaRepositoryGet,
        });

        wrapper.vm.selectedItems = [selectedMedia];
        wrapper.vm.reloadList = jest.fn().mockResolvedValue();
        wrapper.vm.$refs.mediaLibrary.selectableItems = [];
        wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem = jest.fn();

        await wrapper.vm.onChangeLanguage();

        expect(mediaRepositoryGet).toHaveBeenCalledWith('media-id', Shopware.Context.api);
        expect(wrapper.vm.selectedItems).toEqual([translatedMedia]);
        expect(wrapper.vm.$refs.mediaLibrary.setListSelectionStartItem).toHaveBeenCalledWith(translatedMedia);
    });
});

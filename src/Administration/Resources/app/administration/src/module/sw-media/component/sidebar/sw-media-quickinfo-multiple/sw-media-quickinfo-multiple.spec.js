/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';

function createMediaItem(overrides = {}) {
    return {
        id: 'media-id',
        url: 'https://example.com/Test.png',
        fileName: 'Test',
        fileExtension: 'png',
        fileSize: 12345,
        mimeType: 'image/png',
        private: false,
        getEntityName: () => 'media',
        ...overrides,
    };
}

function createFolderItem(overrides = {}) {
    return {
        id: 'folder-id',
        fileSize: 0,
        getEntityName: () => 'media_folder',
        ...overrides,
    };
}

async function createWrapper(items = [createMediaItem()]) {
    return mount(await wrapTestComponent('sw-media-quickinfo-multiple', { sync: true }), {
        global: {
            stubs: {
                'sw-media-collapse': {
                    template: '<div class="sw-media-collapse"><slot name="content"></slot></div>',
                },
                'sw-media-entity-mapper': true,
                'sw-media-modal-delete': true,
                'sw-media-modal-folder-dissolve': true,
                'sw-media-modal-move': true,
                'mt-icon': true,
            },
            provide: {
                acl: {
                    can: () => true,
                },
                mediaService: {},
            },
        },
        props: {
            items,
            editable: true,
        },
    });
}

describe('module/sw-media/component/sidebar/sw-media-quickinfo-multiple', () => {
    beforeEach(() => {
        Shopware.Store.get('actionButtons').buttons = [];
    });

    it('should show list action button from apps', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
        });

        const wrapper = await createWrapper();

        expect(wrapper.find('.quickaction--custom').exists()).toBeTruthy();
    });

    it('should not show item action button in multiselect', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'item',
            label: 'Convert models',
        });

        const wrapper = await createWrapper();

        expect(wrapper.find('.quickaction--custom').exists()).toBeFalsy();
    });

    it('should not show action button when no media item matches the file types', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
            fileTypes: ['step'],
        });

        const wrapper = await createWrapper();

        expect(wrapper.find('.quickaction--custom').exists()).toBeFalsy();
    });

    it('should show action button when all media items match the file types', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
            fileTypes: ['step'],
        });

        const wrapper = await createWrapper([
            createMediaItem({ id: 'a', fileExtension: 'step' }),
            createMediaItem({ id: 'b', fileExtension: 'step' }),
        ]);

        expect(wrapper.find('.quickaction--custom').exists()).toBeTruthy();
    });

    it('should not show action button when only some media items match the file types', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
            fileTypes: ['step'],
        });

        const wrapper = await createWrapper([
            createMediaItem({ id: 'a', fileExtension: 'png' }),
            createMediaItem({ id: 'b', fileExtension: 'step' }),
        ]);

        expect(wrapper.find('.quickaction--custom').exists()).toBeFalsy();
    });

    it('should not show action button when only folders are selected', async () => {
        Shopware.Store.get('actionButtons').add({
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
        });

        const wrapper = await createWrapper([createFolderItem()]);

        expect(wrapper.find('.quickaction--custom').exists()).toBeFalsy();
    });

    it('should call the action button callback with all selected media items', async () => {
        const callback = jest.fn();
        const action = {
            id: 'button-1',
            name: 'media-button',
            entity: 'media',
            view: 'list',
            label: 'Convert models',
            fileTypes: ['step'],
            callback,
        };

        const wrapper = await createWrapper([
            createMediaItem({ id: 'a', fileExtension: 'step', fileName: 'First', url: 'https://example.com/First.step' }),
            createMediaItem({ id: 'b', fileExtension: 'step', fileName: 'Model', url: 'https://example.com/Model.step' }),
            createFolderItem(),
        ]);

        wrapper.vm.runAppAction(action);

        expect(callback).toHaveBeenCalledWith([
            {
                id: 'a',
                url: 'https://example.com/First.step',
                fileName: 'First',
                mimeType: 'image/png',
                fileSize: 12345,
            },
            {
                id: 'b',
                url: 'https://example.com/Model.step',
                fileName: 'Model',
                mimeType: 'image/png',
                fileSize: 12345,
            },
        ]);
    });

    it('should not fail when the action has no callback', async () => {
        const wrapper = await createWrapper();

        expect(() => wrapper.vm.runAppAction({ id: 'button-1', entity: 'media', view: 'list' })).not.toThrow();
    });
});

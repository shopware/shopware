/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

let repositoryFactoryCreateMock;
let repositoryFactorySearchMock;
let repositoryFactorySearchIdsMock;
let repositoryFactorySaveMock;

async function createWrapper({ featureActive = false } = {}) {
    repositoryFactoryCreateMock = jest.fn(() => Promise.resolve());
    repositoryFactorySearchMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySearchIdsMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySaveMock = jest.fn(() => Promise.resolve());

    return mount(
        await wrapTestComponent('sw-media-modal-folder-settings', {
            sync: true,
        }),
        {
            props: {
                mediaFolderId: '12345',
                disabled: false,
            },
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-tabs': await wrapTestComponent('sw-tabs', {
                        sync: true,
                    }),
                    'sw-tabs-item': true,
                    'sw-text-field': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-entity-single-select': true,
                    'sw-container': true,
                    'sw-field': true,
                    'mt-number-field': true,
                    'sw-media-add-thumbnail-form': true,
                    'sw-loader': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'mt-icon': true,
                    'sw-tabs-deprecated': true,
                },
                provide: {
                    feature: {
                        isActive: (flag) => flag === 'v6.8.0.0' && featureActive,
                    },
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                create: (...args) => repositoryFactoryCreateMock(...args),
                                search: (...args) => repositoryFactorySearchMock(...args),
                                searchIds: (...args) => repositoryFactorySearchIdsMock(...args),
                                save: repositoryFactorySaveMock,
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
        },
    );
}

describe('src/app/asyncComponent/media/sw-media-modal-folder-settings', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        await flushPromises();

        expect(wrapper.find('sw-tabs-deprecated-stub').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs and switch active content when the major feature flag is active', async () => {
        wrapper = await createWrapper({ featureActive: true });
        await flushPromises();

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(tabs.exists()).toBe(true);
        expect(tabs.props('positionIdentifier')).toBe('sw-media-modal-folder-settings');
        expect(tabs.props('defaultItem')).toBe('settings');
        expect(tabs.props('items')).toEqual([
            {
                label: 'global.sw-media-modal-folder-settings.labelSettings',
                name: 'settings',
                hasError: false,
            },
            {
                label: 'global.sw-media-modal-folder-settings.labelThumbnails',
                name: 'thumbnails',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);

        await tabs.vm.$emit('new-item-active', 'thumbnails');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('thumbnails');
        expect(wrapper.vm.modalClass).toBe('');
        expect(wrapper.find('.sw-media-modal-folder-settings__thumbnails-container').exists()).toBe(true);
    });

    it('should get thumbnail sizes and unused thumbnail sizes with the correct criteria', async () => {
        const searchIds = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'searchIds');
        const search = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'search');

        const getUnusedThumbnailSizes = jest.spyOn(wrapper.vm, 'getUnusedThumbnailSizes');
        const getThumbnailSizes = jest.spyOn(wrapper.vm, 'getThumbnailSizes');

        await wrapper.vm.createdComponent();

        expect(getUnusedThumbnailSizes).toHaveBeenCalled();
        expect(getThumbnailSizes).toHaveBeenCalled();

        expect(searchIds).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'mediaFolderConfigurations.mediaFolders.id',
                        type: 'equals',
                        value: null,
                    },
                ],
            }),
        );
        expect(search).toHaveBeenCalledWith(
            expect.objectContaining({
                sortings: [
                    {
                        field: 'width',
                        naturalSorting: false,
                        order: 'ASC',
                    },
                ],
            }),
        );
    });

    it('should update thumbnail sizes correctly', async () => {
        repositoryFactorySearchIdsMock = jest.fn(() => {
            return Promise.resolve({
                data: ['12345'],
            });
        });
        repositoryFactorySearchMock = jest.fn(() => {
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
        repositoryFactoryCreateMock = jest.fn(() => {
            return { _isNew: true };
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

        expect(repositoryFactoryCreateMock).toHaveBeenCalled();
        expect(repositoryFactorySaveMock).toHaveBeenCalledWith(
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

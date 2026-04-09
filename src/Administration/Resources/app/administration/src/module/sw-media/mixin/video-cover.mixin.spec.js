/**
 * @sw-package discovery
 */
import 'src/module/sw-media/mixin/video-cover.mixin';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

async function createWrapper(item = {}, mediaServiceFunctions = {}, aclFunctions = {}) {
    const defaultItem = {
        id: 'test-video-id',
        mediaType: {
            name: 'VIDEO',
        },
        mimeType: 'video/mp4',
        metaData: {
            video: {
                coverMediaId: 'test-cover-id',
            },
        },
        isLoading: false,
        ...item,
    };

    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName('notification'),
                Shopware.Mixin.getByName('video-cover'),
            ],
            data() {
                return {
                    item: defaultItem,
                };
            },
        },
        {
            global: {
                provide: {
                    mediaService: {
                        assignVideoCover: jest.fn(() => Promise.resolve()),
                        ...mediaServiceFunctions,
                    },
                    acl: {
                        can: jest.fn(() => true),
                        ...aclFunctions,
                    },
                },
                stubs: {},
            },
            attachTo: document.body,
        },
    );
}

describe('src/module/sw-media/mixin/video-cover.mixin.js', () => {
    let wrapper;
    let createNotificationSpy;
    let eventBusEmitSpy;

    beforeEach(async () => {
        setActivePinia(createPinia());
        createNotificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        eventBusEmitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }
        jest.clearAllMocks();
    });

    it('should register the mixin', () => {
        expect(Shopware.Mixin.getByName('video-cover')).toBeDefined();
    });

    it('should have showCoverSelectionModal in data', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm.showCoverSelectionModal).toBe(false);
    });

    it('should compute isVideoMedia correctly for video items', async () => {
        wrapper = await createWrapper({
            mediaType: { name: 'VIDEO' },
        });
        expect(wrapper.vm.isVideoMedia).toBe(true);
    });

    it('should compute isVideoMedia correctly for non-video items', async () => {
        wrapper = await createWrapper({
            mediaType: { name: 'IMAGE' },
        });
        expect(wrapper.vm.isVideoMedia).toBe(false);
    });

    it('should compute hasVideoCover correctly when cover exists', async () => {
        wrapper = await createWrapper({
            metaData: {
                video: {
                    coverMediaId: 'test-cover-id',
                },
            },
        });
        expect(wrapper.vm.hasVideoCover).toBe(true);
    });

    it('should compute hasVideoCover correctly when cover does not exist', async () => {
        wrapper = await createWrapper({
            metaData: {
                video: {},
            },
        });
        expect(wrapper.vm.hasVideoCover).toBe(false);
    });

    it('should open cover selection modal when user has permission', async () => {
        wrapper = await createWrapper(
            {},
            {},
            {
                can: jest.fn(() => true),
            },
        );
        wrapper.vm.openCoverSelectionModal();
        expect(wrapper.vm.showCoverSelectionModal).toBe(true);
    });

    it('should not open cover selection modal when user lacks permission', async () => {
        wrapper = await createWrapper(
            {},
            {},
            {
                can: jest.fn(() => false),
            },
        );
        wrapper.vm.openCoverSelectionModal();
        expect(wrapper.vm.showCoverSelectionModal).toBe(false);
    });

    it('should close cover selection modal', async () => {
        wrapper = await createWrapper();
        wrapper.vm.showCoverSelectionModal = true;
        wrapper.vm.closeCoverSelectionModal();
        expect(wrapper.vm.showCoverSelectionModal).toBe(false);
    });

    it('should call persistCoverMedia with image id on cover selection change', async () => {
        const persistCoverMediaSpy = jest.fn();
        wrapper = await createWrapper();
        wrapper.vm.persistCoverMedia = persistCoverMediaSpy;

        const imageMedia = {
            id: 'test-image-id',
            mediaType: { name: 'IMAGE' },
        };

        await wrapper.vm.onCoverSelectionChange([imageMedia]);

        expect(persistCoverMediaSpy).toHaveBeenCalledWith('test-image-id');
        expect(wrapper.vm.showCoverSelectionModal).toBe(false);
    });

    it('should show error notification for invalid cover selection', async () => {
        wrapper = await createWrapper();

        const invalidMedia = {
            id: 'test-invalid-id',
            mediaType: { name: 'VIDEO' },
        };

        await wrapper.vm.onCoverSelectionChange([invalidMedia]);

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
            }),
        );
    });

    it('should persist cover media successfully', async () => {
        const assignVideoCoverSpy = jest.fn(() => Promise.resolve());
        wrapper = await createWrapper(
            {},
            {
                assignVideoCover: assignVideoCoverSpy,
            },
        );

        await wrapper.vm.persistCoverMedia('test-cover-id');

        expect(assignVideoCoverSpy).toHaveBeenCalledWith('test-video-id', 'test-cover-id');
        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'success',
            }),
        );
        expect(eventBusEmitSpy).toHaveBeenCalledWith('sw-media-library-item-updated', 'test-video-id');
    });

    it('should remove cover media successfully', async () => {
        const assignVideoCoverSpy = jest.fn(() => Promise.resolve());
        wrapper = await createWrapper(
            {},
            {
                assignVideoCover: assignVideoCoverSpy,
            },
        );

        await wrapper.vm.removeVideoCover();

        expect(assignVideoCoverSpy).toHaveBeenCalledWith('test-video-id', null);
        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'success',
            }),
        );
        expect(eventBusEmitSpy).toHaveBeenCalledWith('sw-media-library-item-updated', 'test-video-id');
    });

    it('should handle error when persisting cover media', async () => {
        const assignVideoCoverSpy = jest.fn(() => Promise.reject(new Error('API Error')));
        wrapper = await createWrapper(
            {},
            {
                assignVideoCover: assignVideoCoverSpy,
            },
        );

        await wrapper.vm.persistCoverMedia('test-cover-id');

        expect(assignVideoCoverSpy).toHaveBeenCalledWith('test-video-id', 'test-cover-id');
        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
            }),
        );
        expect(eventBusEmitSpy).not.toHaveBeenCalled();
    });

    it('should not persist cover media if not video media', async () => {
        const assignVideoCoverSpy = jest.fn();
        wrapper = await createWrapper(
            {
                mediaType: { name: 'IMAGE' },
            },
            {
                assignVideoCover: assignVideoCoverSpy,
            },
        );

        await wrapper.vm.persistCoverMedia('test-cover-id');

        expect(assignVideoCoverSpy).not.toHaveBeenCalled();
    });

    it('should check if media is image by mediaType', async () => {
        wrapper = await createWrapper();

        const imageMedia = {
            mediaType: { name: 'IMAGE' },
        };

        expect(wrapper.vm.isImage(imageMedia)).toBe(true);
    });

    it('should check if media is image by mimeType', async () => {
        wrapper = await createWrapper();

        const imageMedia = {
            mimeType: 'image/png',
        };

        expect(wrapper.vm.isImage(imageMedia)).toBe(true);
    });

    it('should check if item is video by mediaType', async () => {
        wrapper = await createWrapper();

        const videoItem = {
            mediaType: { name: 'VIDEO' },
        };

        expect(wrapper.vm.isVideo(videoItem)).toBe(true);
    });

    it('should check if item is video by mimeType', async () => {
        wrapper = await createWrapper();

        const videoItem = {
            mimeType: 'video/mp4',
        };

        expect(wrapper.vm.isVideo(videoItem)).toBe(true);
    });

    it('should get cover media id from item metadata', async () => {
        wrapper = await createWrapper();

        const itemWithCover = {
            metaData: {
                video: {
                    coverMediaId: 'test-cover-id',
                },
            },
        };

        expect(wrapper.vm.getCoverMediaId(itemWithCover)).toBe('test-cover-id');
    });

    it('should return null when cover media id does not exist', async () => {
        wrapper = await createWrapper();

        const itemWithoutCover = {
            metaData: {
                video: {},
            },
        };

        expect(wrapper.vm.getCoverMediaId(itemWithoutCover)).toBeNull();
    });

    it('should set item isLoading during persistCoverMedia', async () => {
        const assignVideoCoverSpy = jest.fn(
            () =>
                new Promise((resolve) => {
                    setTimeout(resolve, 100);
                }),
        );
        wrapper = await createWrapper(
            {},
            {
                assignVideoCover: assignVideoCoverSpy,
            },
        );

        const persistPromise = wrapper.vm.persistCoverMedia('test-cover-id');

        expect(wrapper.vm.item.isLoading).toBe(true);

        await persistPromise;

        expect(wrapper.vm.item.isLoading).toBe(false);
    });
});

/**
 * @sw-package discovery
 */
import useVideoCover, { type VideoCoverMedia } from './use-video-cover';

const createNotificationSuccess = jest.fn();
const createNotificationError = jest.fn();

jest.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key }),
}));

jest.mock('./use-notification', () => ({
    __esModule: true,
    default: () => ({ createNotificationSuccess, createNotificationError }),
}));

const assignVideoCover = jest.fn();
const emit = jest.fn();

function stubShopware(can = true): void {
    window.Shopware = {
        Service: jest.fn((name: string) => (name === 'acl' ? { can: () => can } : { assignVideoCover })),
        Utils: { EventBus: { emit } },
    } as unknown as typeof Shopware;
}

function videoItem(overrides: VideoCoverMedia = {}): VideoCoverMedia {
    return { id: 'video-1', mediaType: { name: 'VIDEO' }, ...overrides };
}

describe('src/app/composables/use-video-cover', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        assignVideoCover.mockResolvedValue(undefined);
        stubShopware();
    });

    it.each([
        [
            { mediaType: { name: 'VIDEO' } },
            true,
        ],
        [
            { mediaType: { name: 'IMAGE' } },
            false,
        ],
        [
            { mimeType: 'video/mp4' },
            true,
        ],
        [
            { mimeType: 'image/png' },
            false,
        ],
    ])('recognizes a video by media type and mime type', (item: VideoCoverMedia, expected: boolean) => {
        const { isVideoMedia } = useVideoCover({ item: () => item });

        expect(isVideoMedia.value).toBe(expected);
    });

    it('reads the cover id out of the video metadata', () => {
        const item = videoItem({ metaData: { video: { coverMediaId: 'cover-1' } } });
        const { hasVideoCover, getCoverMediaId } = useVideoCover({ item: () => item });

        expect(hasVideoCover.value).toBe(true);
        expect(getCoverMediaId(item)).toBe('cover-1');
        expect(getCoverMediaId(videoItem())).toBeNull();
    });

    it('opens the selection modal only with the media.editor privilege', () => {
        stubShopware(false);
        const denied = useVideoCover({ item: () => videoItem() });

        denied.openCoverSelectionModal();

        expect(denied.showCoverSelectionModal.value).toBe(false);

        stubShopware(true);
        const granted = useVideoCover({ item: () => videoItem() });

        granted.openCoverSelectionModal();

        expect(granted.showCoverSelectionModal.value).toBe(true);
    });

    it('assigns the selected image as the cover and announces the update', async () => {
        const item = videoItem();
        const { onCoverSelectionChange, showCoverSelectionModal } = useVideoCover({ item: () => item });

        showCoverSelectionModal.value = true;
        await onCoverSelectionChange([{ id: 'cover-1', mediaType: { name: 'IMAGE' } }]);

        expect(showCoverSelectionModal.value).toBe(false);
        expect(assignVideoCover).toHaveBeenCalledWith('video-1', 'cover-1');
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSaveSuccess.message',
        });
        expect(emit).toHaveBeenCalledWith('sw-media-library-item-updated', 'video-1');
        expect(item.isLoading).toBe(false);
    });

    it('rejects a selection that is not an image', async () => {
        const item = videoItem();
        const { onCoverSelectionChange } = useVideoCover({ item: () => item });

        await onCoverSelectionChange([{ id: 'other-1', mediaType: { name: 'VIDEO' } }]);

        expect(assignVideoCover).not.toHaveBeenCalled();
        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSelectionInvalid.message',
        });
    });

    it('removes the cover by assigning null', async () => {
        const item = videoItem();
        const { removeVideoCover } = useVideoCover({ item: () => item });

        await removeVideoCover();

        expect(assignVideoCover).toHaveBeenCalledWith('video-1', null);
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverRemoveSuccess.message',
        });
    });

    it('reports a failed assignment and stops the item loading', async () => {
        assignVideoCover.mockRejectedValue(new Error('nope'));
        const item = videoItem();
        const { persistCoverMedia } = useVideoCover({ item: () => item });

        await persistCoverMedia('cover-1');

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSaveError.message',
        });
        expect(item.isLoading).toBe(false);
    });

    it.each([
        [
            'a non-video item',
            { id: 'image-1', mediaType: { name: 'IMAGE' } },
        ],
        [
            'an item without an id',
            { mediaType: { name: 'VIDEO' } },
        ],
    ])('does not assign a cover for %s', async (_case: string, item: VideoCoverMedia) => {
        const { persistCoverMedia } = useVideoCover({ item: () => item });

        await persistCoverMedia('cover-1');

        expect(assignVideoCover).not.toHaveBeenCalled();
    });
});

/**
 * @sw-package discovery
 */
import { useNotification } from 'src/app/composables/use-notification';
import { useVideoCover } from './use-video-cover';
import type { VideoCoverMediaItem } from './use-video-cover';

jest.mock('vue-i18n', () => {
    const actual: object = jest.requireActual('vue-i18n');

    return { ...actual, useI18n: () => ({ t: (key: string) => key }) };
});

jest.mock('src/app/composables/use-notification', () => ({
    useNotification: jest.fn(),
}));

const createNotificationSuccess = jest.fn();
const createNotificationError = jest.fn();
const assignVideoCover = jest.fn().mockResolvedValue(undefined);

function createComposable(item: VideoCoverMediaItem | null, canEdit = true) {
    (useNotification as jest.Mock).mockReturnValue({ createNotificationSuccess, createNotificationError });

    jest.spyOn(Shopware, 'Service').mockImplementation(
        (name?: string) => (name === 'acl' ? { can: () => canEdit } : { assignVideoCover }) as never,
    );

    return useVideoCover({ item: () => item });
}

const videoItem: VideoCoverMediaItem = {
    id: 'media-1',
    mediaType: { name: 'VIDEO' },
    metaData: { video: { coverMediaId: null } },
};

describe('src/module/sw-media/composables/use-video-cover', () => {
    afterEach(() => {
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    it('detects video items and their cover from the item getter', () => {
        const composable = createComposable({ ...videoItem, metaData: { video: { coverMediaId: 'cover-1' } } });

        expect(composable.isVideoMedia.value).toBe(true);
        expect(composable.hasVideoCover.value).toBe(true);
    });

    it('falls back to the mime type when no media type is set', () => {
        const composable = createComposable({ mimeType: 'video/mp4' });

        expect(composable.isVideoMedia.value).toBe(true);
        expect(composable.isImage({ mimeType: 'image/png' })).toBe(true);
        expect(composable.isImage({ mimeType: 'video/mp4' })).toBe(false);
    });

    it('opens the cover selection modal only for media editors', () => {
        const denied = createComposable(videoItem, false);
        denied.openCoverSelectionModal();
        expect(denied.showCoverSelectionModal.value).toBe(false);

        const granted = createComposable(videoItem);
        granted.openCoverSelectionModal();
        expect(granted.showCoverSelectionModal.value).toBe(true);
    });

    it('assigns the selected cover and reports success', async () => {
        const item = { ...videoItem };
        const composable = createComposable(item);

        await composable.onCoverSelectionChange([{ id: 'cover-1', mediaType: { name: 'IMAGE' } }]);

        expect(assignVideoCover).toHaveBeenCalledWith('media-1', 'cover-1');
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSaveSuccess.message',
        });
        expect(composable.showCoverSelectionModal.value).toBe(false);
        expect(item.isLoading).toBe(false);
    });

    it('rejects a non-image selection without touching the media service', async () => {
        const composable = createComposable(videoItem);

        await composable.onCoverSelectionChange([{ id: 'other-video', mediaType: { name: 'VIDEO' } }]);

        expect(assignVideoCover).not.toHaveBeenCalled();
        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSelectionInvalid.message',
        });
    });

    it('removes the cover and reports the remove message', async () => {
        const composable = createComposable(videoItem);

        await composable.removeVideoCover();

        expect(assignVideoCover).toHaveBeenCalledWith('media-1', null);
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverRemoveSuccess.message',
        });
    });

    it('reports an error and stops loading when the media service fails', async () => {
        const item = { ...videoItem };
        const composable = createComposable(item);
        assignVideoCover.mockRejectedValueOnce(new Error('nope'));

        await composable.persistCoverMedia('cover-1');

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.coverSaveError.message',
        });
        expect(item.isLoading).toBe(false);
    });

    it('does nothing for an item that is not a video', async () => {
        const composable = createComposable({ id: 'media-1', mediaType: { name: 'IMAGE' } });

        await composable.persistCoverMedia('cover-1');

        expect(assignVideoCover).not.toHaveBeenCalled();
    });
});

/**
 * @sw-package discovery
 */
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

interface VideoCoverMediaService {
    assignVideoCover: (mediaId: string, coverMediaId: string | null) => Promise<unknown>;
}

/** @private */
export interface VideoCoverMediaItem {
    id?: string;
    isLoading?: boolean;
    mediaType?: { name?: string };
    mimeType?: string;
    metaData?: { video?: { coverMediaId?: string | null } };
}

/**
 * The mixin read the media item from the host component's `item` prop. The
 * composable takes it as a getter so it stays reactive.
 *
 * @private
 */
export interface UseVideoCoverOptions {
    item: () => VideoCoverMediaItem | null | undefined;
}

/** @private */
export interface UseVideoCoverReturn {
    showCoverSelectionModal: Ref<boolean>;
    isVideoMedia: ComputedRef<boolean>;
    hasVideoCover: ComputedRef<boolean>;
    openCoverSelectionModal: () => void;
    closeCoverSelectionModal: () => void;
    onCoverSelectionChange: (selection: VideoCoverMediaItem[]) => Promise<void>;
    persistCoverMedia: (coverMediaId: string | null) => Promise<void>;
    isImage: (media?: VideoCoverMediaItem | null) => boolean;
    isVideo: (item?: VideoCoverMediaItem | null) => boolean;
    removeVideoCover: () => Promise<void>;
    getCoverMediaId: (item?: VideoCoverMediaItem | null) => string | null;
}

/**
 * Composable alternative to the `video-cover` mixin: assigns and removes the
 * cover image of a video media item. The mixin injected `mediaService`/`acl` and
 * read `this.item`; here the services come from `Shopware.Service` and the item
 * is passed in as a getter, because a composable has no component instance.
 *
 * Keep this and `src/module/sw-media/mixin/video-cover.mixin.js` in sync —
 * change both together.
 *
 * @private
 */
export function useVideoCover(options: UseVideoCoverOptions): UseVideoCoverReturn {
    const { t } = useI18n();
    const { createNotificationSuccess, createNotificationError } = useNotification();

    const showCoverSelectionModal = ref(false);

    function acl(): { can: (privilege: string) => boolean } {
        return Shopware.Service('acl');
    }

    function mediaService(): VideoCoverMediaService {
        // `mediaService` is registered in the DI container but missing from its type map.
        return Shopware.Service('mediaService' as keyof ServiceContainer) as unknown as VideoCoverMediaService;
    }

    function isImage(media?: VideoCoverMediaItem | null): boolean {
        const typeName = media?.mediaType?.name;

        if (typeName) {
            return typeName === 'IMAGE';
        }

        return media?.mimeType?.startsWith('image/') ?? false;
    }

    function isVideo(item?: VideoCoverMediaItem | null): boolean {
        const typeName = item?.mediaType?.name;

        if (typeName) {
            return typeName === 'VIDEO';
        }

        return item?.mimeType?.startsWith('video/') ?? false;
    }

    function getCoverMediaId(item?: VideoCoverMediaItem | null): string | null {
        return item?.metaData?.video?.coverMediaId ?? null;
    }

    const isVideoMedia = computed(() => isVideo(options.item()));

    const hasVideoCover = computed(() => getCoverMediaId(options.item()) !== null);

    function openCoverSelectionModal(): void {
        if (!acl().can('media.editor')) {
            return;
        }

        showCoverSelectionModal.value = true;
    }

    function closeCoverSelectionModal(): void {
        showCoverSelectionModal.value = false;
    }

    async function persistCoverMedia(coverMediaId: string | null): Promise<void> {
        const item = options.item();

        if (!isVideoMedia.value || !item?.id) {
            return;
        }

        item.isLoading = true;

        try {
            await mediaService().assignVideoCover(item.id, coverMediaId);

            const snippetKey = coverMediaId
                ? 'global.sw-media-media-item.notification.coverSaveSuccess.message'
                : 'global.sw-media-media-item.notification.coverRemoveSuccess.message';

            createNotificationSuccess({
                message: t(snippetKey),
            });

            Shopware.Utils.EventBus.emit('sw-media-library-item-updated', item.id);
        } catch {
            createNotificationError({
                message: t('global.sw-media-media-item.notification.coverSaveError.message'),
            });
        } finally {
            item.isLoading = false;
        }
    }

    async function onCoverSelectionChange(selection: VideoCoverMediaItem[]): Promise<void> {
        const [media] = selection;
        closeCoverSelectionModal();

        if (!media || !isImage(media)) {
            createNotificationError({
                message: t('global.sw-media-media-item.notification.coverSelectionInvalid.message'),
            });

            return;
        }

        await persistCoverMedia(media.id ?? null);
    }

    async function removeVideoCover(): Promise<void> {
        await persistCoverMedia(null);
    }

    return {
        showCoverSelectionModal,
        isVideoMedia,
        hasVideoCover,
        openCoverSelectionModal,
        closeCoverSelectionModal,
        onCoverSelectionChange,
        persistCoverMedia,
        isImage,
        isVideo,
        removeVideoCover,
        getCoverMediaId,
    };
}

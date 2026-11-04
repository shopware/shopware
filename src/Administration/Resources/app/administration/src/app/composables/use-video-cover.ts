/**
 * @sw-package discovery
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import useNotification from './use-notification';

/** @private */
export type VideoCoverMedia = {
    id?: EntityKey<'media'>;
    isLoading?: boolean;
    mediaType?: { name?: string } | null;
    mimeType?: string | null;
    metaData?: { video?: { coverMediaId?: string | null } | null } | null;
};

/**
 * The mixin read the media item off `this.item`, a prop of its host. The composable takes a getter so
 * the item stays reactive and so writes to `isLoading` still land on the entity the host passed.
 *
 * @private
 */
export interface UseVideoCoverOptions {
    item: () => VideoCoverMedia | null | undefined;
}

/**
 * Composable alternative to the `video-cover` mixin: assigns and removes the poster image of a video
 * media item. The mixin stays in place for Options API components.
 *
 * The mixin injected `mediaService` and `acl` and reached for the host's `createNotification*` helpers;
 * this composable resolves the services through `Shopware.Service` and the notifications through
 * `useNotification`, so an override of a notification helper no longer reaches its messages.
 *
 * Keep this and `src/module/sw-media/mixin/video-cover.mixin.js` in sync — change both together.
 *
 * @private
 */
export default function useVideoCover(options: UseVideoCoverOptions): {
    showCoverSelectionModal: Ref<boolean>;
    isVideoMedia: ComputedRef<boolean>;
    hasVideoCover: ComputedRef<boolean>;
    openCoverSelectionModal: () => void;
    closeCoverSelectionModal: () => void;
    onCoverSelectionChange: (selection: VideoCoverMedia[]) => Promise<void>;
    persistCoverMedia: (coverMediaId: string | null) => Promise<void>;
    isImage: (media?: VideoCoverMedia | null) => boolean;
    isVideo: (item?: VideoCoverMedia | null) => boolean;
    removeVideoCover: () => Promise<void>;
    getCoverMediaId: (item?: VideoCoverMedia | null) => string | null;
} {
    const i18n = useI18n();
    const { createNotificationSuccess, createNotificationError } = useNotification();
    const showCoverSelectionModal = ref(false);

    function isImage(media?: VideoCoverMedia | null): boolean {
        const typeName = media?.mediaType?.name;

        if (typeName) {
            return typeName === 'IMAGE';
        }

        return media?.mimeType?.startsWith('image/') ?? false;
    }

    function isVideo(item?: VideoCoverMedia | null): boolean {
        const typeName = item?.mediaType?.name;

        if (typeName) {
            return typeName === 'VIDEO';
        }

        return item?.mimeType?.startsWith('video/') ?? false;
    }

    function getCoverMediaId(item?: VideoCoverMedia | null): string | null {
        return item?.metaData?.video?.coverMediaId ?? null;
    }

    const isVideoMedia = computed(() => isVideo(options.item()));
    const hasVideoCover = computed(() => getCoverMediaId(options.item()) !== null);

    function openCoverSelectionModal(): void {
        if (!Shopware.Service('acl').can('media.editor')) {
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
            await Shopware.Service('mediaService').assignVideoCover(item.id, coverMediaId);

            const snippetKey = coverMediaId
                ? 'global.sw-media-media-item.notification.coverSaveSuccess.message'
                : 'global.sw-media-media-item.notification.coverRemoveSuccess.message';

            createNotificationSuccess({ message: i18n.t(snippetKey) });

            Shopware.Utils.EventBus.emit('sw-media-library-item-updated', item.id);
        } catch {
            createNotificationError({
                message: i18n.t('global.sw-media-media-item.notification.coverSaveError.message'),
            });
        } finally {
            item.isLoading = false;
        }
    }

    async function onCoverSelectionChange(selection: VideoCoverMedia[]): Promise<void> {
        const [media] = selection;

        closeCoverSelectionModal();

        if (!media || !isImage(media)) {
            createNotificationError({
                message: i18n.t('global.sw-media-media-item.notification.coverSelectionInvalid.message'),
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

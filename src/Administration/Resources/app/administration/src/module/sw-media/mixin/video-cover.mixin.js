/**
 * @sw-package discovery
 */
Shopware.Mixin.register('video-cover', {
    inject: [
        'mediaService',
        'acl',
    ],

    data() {
        return {
            showCoverSelectionModal: false,
        };
    },

    computed: {
        isVideoMedia() {
            return this.isVideo(this.item);
        },

        hasVideoCover() {
            return this.getCoverMediaId(this.item) !== null;
        },
    },

    methods: {
        openCoverSelectionModal() {
            if (!this.acl.can('media.editor')) {
                return;
            }

            this.showCoverSelectionModal = true;
        },

        closeCoverSelectionModal() {
            this.showCoverSelectionModal = false;
        },

        async onCoverSelectionChange(selection) {
            const [media] = selection;
            this.closeCoverSelectionModal();

            if (!media || !this.isImage(media)) {
                this.createNotificationError({
                    message: this.$t('global.sw-media-media-item.notification.coverSelectionInvalid.message'),
                });

                return;
            }

            await this.persistCoverMedia(media.id);
        },

        async persistCoverMedia(coverMediaId) {
            if (!this.isVideoMedia || !this.item?.id) {
                return;
            }

            this.item.isLoading = true;

            try {
                await this.mediaService.assignVideoCover(this.item.id, coverMediaId);

                const snippetKey = coverMediaId
                    ? 'global.sw-media-media-item.notification.coverSaveSuccess.message'
                    : 'global.sw-media-media-item.notification.coverRemoveSuccess.message';

                this.createNotificationSuccess({
                    message: this.$t(snippetKey),
                });

                Shopware.Utils.EventBus.emit('sw-media-library-item-updated', this.item.id);
            } catch {
                this.createNotificationError({
                    message: this.$t('global.sw-media-media-item.notification.coverSaveError.message'),
                });
            } finally {
                this.item.isLoading = false;
            }
        },

        isImage(media) {
            const typeName = media?.mediaType?.name;

            if (typeName) {
                return typeName === 'IMAGE';
            }

            return media?.mimeType?.startsWith('image/') ?? false;
        },

        isVideo(item) {
            const typeName = item?.mediaType?.name;

            if (typeName) {
                return typeName === 'VIDEO';
            }

            return item?.mimeType?.startsWith('video/') ?? false;
        },

        async removeVideoCover() {
            await this.persistCoverMedia(null);
        },

        getCoverMediaId(item) {
            return item?.metaData?.video?.coverMediaId ?? null;
        },
    },
});

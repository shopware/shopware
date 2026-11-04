/**
 * @sw-package inventory
 */
import type { PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-product-document-download.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,
    inject: ['acl', 'mediaService'],
    props: {
        media: {
            type: Object as PropType<Entity<'media'>>,
            default: null,
        },
    },
    methods: {
        async downloadDocument(): Promise<void> {
            const media = this.media;
            if (!this.acl.can('media.viewer') || !media) {
                return;
            }

            try {
                const downloadConfig = (await this.mediaService.prepareDownloadMedia(media.id)) as
                    | { type: 'external'; url: string }
                    | { type: 'blob' };
                if (downloadConfig.type === 'external') {
                    this.triggerDownload(downloadConfig.url);
                    return;
                }

                const data = (await this.mediaService.downloadMedia(media.id)) as Blob;
                const url = URL.createObjectURL(data);
                try {
                    this.triggerDownload(url, [media.fileName, media.fileExtension].filter(Boolean).join('.'));
                } finally {
                    URL.revokeObjectURL(url);
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('global.sw-media-media-item.notification.downloadError.message'),
                });
            }
        },

        triggerDownload(url: string, fileName?: string): void {
            const link = document.createElement('a');
            link.href = url;
            if (fileName) {
                link.download = fileName;
            } else {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
            link.dispatchEvent(new MouseEvent('click'));
            link.remove();
        },
    },
});

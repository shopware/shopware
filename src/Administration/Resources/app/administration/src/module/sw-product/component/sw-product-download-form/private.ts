import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-download-form',
    load: () => import('./src/sw-product-download-form'),
    public: {
        props: [
            'disabled',
            'isInherited',
        ],
        emits: [
            'media-open',
        ],
        blocks: [
            'sw_product_download_form',
        ],
        computed: [
            'product',
            'isStoreLoading',
            'isLoading',
            'productDownloadRepository',
            'productDownloads',
            'mediaRepository',
            'error',
            'hasError',
            'swFieldClasses',
            'fileAccept',
        ],
        methods: [
            'createdComponent',
            'onOpenMedia',
            'getFileSize',
            'getFileName',
            'createdAt',
            'onRemoveDownload',
            'successfulUpload',
            'createDownloadAssociation',
            'onUploadFailed',
            'removeFile',
            'updateMediaItemPositions',
            'downloadMedia',
            'triggerDownload',
        ],
    },
});

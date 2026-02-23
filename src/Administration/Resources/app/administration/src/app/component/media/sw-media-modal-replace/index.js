import template from './sw-media-modal-replace.html.twig';
import './sw-media-modal-replace.scss';

const { Mixin } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-modal-replace</u> component is used to let the user upload a new image for an
 * existing media object.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-modal-replace itemToReplace="item">
 * </sw-media-modal-replace>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mediaService',
        'mediaPresignedUploadService',
        'repositoryFactory',
    ],

    emits: [
        'media-replace-modal-close',
        'media-replace-modal-item-replaced',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        itemToReplace: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            uploadTag: null,
            isUploadDataSet: false,
            newFileExtension: '',
            pendingPresignedFile: null,
        };
    },

    computed: {
        presignedSupported() {
            return Shopware.Store.get('context').app.config?.settings?.presignedUploadSupported ?? false;
        },
    },

    methods: {
        onNewUpload({ data }) {
            this.isUploadDataSet = true;

            const newFileExtension = data[0].extension;
            const oldFileExtension = this.itemToReplace.fileExtension;

            if (newFileExtension !== oldFileExtension) {
                this.newFileExtension = newFileExtension;
            }

            if (this.presignedSupported && data[0].src instanceof File) {
                this.pendingPresignedFile = data[0].src;
            }
        },

        emitCloseReplaceModal() {
            this.$emit('media-replace-modal-close');
        },

        async replaceMediaItem() {
            this.itemToReplace.isLoading = true;
            const previousName = this.itemToReplace.fileName;

            if (this.pendingPresignedFile) {
                await this.runPresignedReplace(this.pendingPresignedFile);
            } else {
                await this.mediaService.runUploads(this.itemToReplace.id);
            }

            await this.mediaService.renameMedia(this.itemToReplace.id, previousName);

            this.itemToReplace.isLoading = false;
            this.$emit('media-replace-modal-item-replaced');
        },

        async runPresignedReplace(fileHandle) {
            const { fileReader } = Shopware.Utils;
            const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
            const mimeType = fileHandle.type || 'application/octet-stream';

            const result = await this.mediaPresignedUploadService.prepareUpload({
                fileName,
                extension,
                mimeType,
                mediaId: this.itemToReplace.id,
            });

            await this.mediaPresignedUploadService.uploadToPresignedUrl(result.url, fileHandle, mimeType);

            await this.mediaPresignedUploadService.finalizeUpload(this.itemToReplace.id, {
                fileName,
                extension,
                mimeType,
                path: result.path,
            });
        },
    },
};

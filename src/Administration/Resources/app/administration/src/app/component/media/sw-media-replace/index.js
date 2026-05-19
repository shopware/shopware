/**
 * @public
 * @status ready
 * @description The <u>sw-media-replace</u> component extends the <u>sw-media-upload</u> component. It is
 * used in cases of replacing items rather than uploading them.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-replace
 *      :item-to-replace="mediaItem"
 *      variant="regular"
 * ></sw-media-replace>
 */
const { fileReader } = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    props: {
        itemToReplace: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            multiSelect: false,
        };
    },

    methods: {
        getMediaEntityForUpload() {
            return this.itemToReplace;
        },

        cleanUpFailure(mediaEntity, message) {
            this.createNotificationError({ message });
        },

        handlePresignedUpload(files) {
            const { extension } = fileReader.getNameAndExtensionFromFile(files[0]);

            this.mediaService.getListenerForTag(this.uploadTag).forEach((listener) => {
                listener(
                    this.mediaService._createUploadEvent('media-upload-add', this.uploadTag, {
                        data: [{ targetId: this.itemToReplace.id, extension, src: files[0] }],
                    }),
                );
            });
        },
    },
};

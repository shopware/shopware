/**
 * @public
 * @status ready
 * @description The <u>sw-media-replace</u> component extends the <u>sw-media-upload</u> component. It is
 * used in cases of replacing items rather than uploading them.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-replace
 *      :item-to-replace="mediaItem"
 *      variant="regular"
 * ></sw-media-replace>
 */
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
    },
};

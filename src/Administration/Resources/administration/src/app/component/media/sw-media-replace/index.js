/**
 * @status ready
 * @description The <u>sw-media-replace</u> component extends the <u>sw-media-upload</u> component. It is
 * used in cases of replacing items rather than uploading them.
 * @example-type static
 * @component-example
 * <sw-media-replace itemToReplace="mediaItem" variant="regular">
 * </sw-media-replace>
 */
export default {
    name: 'sw-media-replace',
    extendsFrom: 'sw-media-upload',
    props: {
        itemToReplace: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            multiSelect: false
        };
    },

    methods: {
        getMediaEntityForUpload() {
            return this.itemToReplace;
        },

        cleanUpFailure(mediaEntity, message) {
            this.createNotificationError({ message });
        }
    }
};

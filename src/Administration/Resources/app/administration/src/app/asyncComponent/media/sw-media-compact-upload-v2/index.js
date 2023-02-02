import template from './sw-media-compact-upload-v2.html.twig';
import './sw-media-compact-upload-v2.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: false,
        },

        disableDeletionForLastItem: {
            type: Object,
            validator(value) {
                return Object(value).hasOwnProperty('value') && Object(value).hasOwnProperty('helpText');
            },
            required: false,
            default: () => {
                return {
                    value: false,
                    helpText: null,
                };
            },
        },

        variant: {
            type: String,
            required: false,
            validValues: ['compact', 'regular'],
            validator(value) {
                return ['compact', 'regular'].includes(value);
            },
            default: 'regular',
        },

        source: {
            type: [String, Object],
            required: false,
            default: '',
        },

        sourceMultiselect: {
            type: Array,
            required: false,
            default: () => { return []; },
        },

        fileAccept: {
            type: String,
            required: false,
            default: 'image/*',
        },

        removeButtonLabel: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            mediaModalIsOpen: false,
        };
    },

    computed: {
        mediaPreview() {
            // for single use
            if (!this.allowMultiSelect) {
                if (this.source) {
                    return this.source;
                }

                return this.preview;
            }

            // for multi upload use
            if (this.sourceMultiselect) {
                return this.sourceMultiselect;
            }

            return null;
        },

        removeFileButtonLabel() {
            if (this.removeButtonLabel === '') {
                return this.$tc('global.sw-product-image.context.buttonRemove');
            }

            return this.removeButtonLabel;
        },

        isDeletionDisabled() {
            if (!this.disableDeletionForLastItem.value) {
                return false;
            }

            return this.sourceMultiselect.length <= 1;
        },
    },

    methods: {
        onModalClosed(selection) {
            this.$emit('selection-change', selection, this.uploadTag);
        },

        getFileName(item) {
            if (item.name) {
                return item.name;
            }

            return `${item.fileName}.${item.fileExtension}`;
        },
    },
};

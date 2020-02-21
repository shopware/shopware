import template from './sw-media-compact-upload.html.twig';
import './sw-media-compact-upload.scss';

/** @deprecated tag:v6.4.0 */
Shopware.Component.extend('sw-media-compact-upload', 'sw-media-upload', {
    template,

    deprecated: {
        version: '6.4.0',
        comment: 'Use sw-media-compact-upload-v2 instead'
    },

    data() {
        return {
            mediaModalIsOpen: false
        };
    },

    props: {
        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: false
        },

        variant: {
            type: String,
            required: false,
            validValues: ['compact', 'regular'],
            validator(value) {
                return ['compact', 'regular'].includes(value);
            },
            default: 'regular'
        },

        source: {
            type: [String, Object],
            required: false,
            default: ''
        }
    },

    methods: {
        onModalClosed(selection) {
            this.$emit('selection-change', selection, this.uploadTag);
        }
    }
});

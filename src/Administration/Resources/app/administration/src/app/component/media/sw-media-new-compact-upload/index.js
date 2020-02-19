import template from './sw-media-new-compact-upload.html.twig';
import './sw-media-new-compact-upload.scss';

Shopware.Component.extend('sw-media-new-compact-upload', 'sw-media-new-upload', {
    template,

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

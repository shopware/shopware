import template from './sw-media-compact-upload-v2.html.twig';
import './sw-media-compact-upload-v2.scss';

Shopware.Component.extend('sw-media-compact-upload-v2', 'sw-media-upload-v2', {
    template,

    props: {
        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: false,
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

        fileAccept: {
            type: String,
            required: false,
            default: 'image/*',
        },
    },

    data() {
        return {
            mediaModalIsOpen: false,
        };
    },

    methods: {
        onModalClosed(selection) {
            this.$emit('selection-change', selection, this.uploadTag);
        },
    },
});

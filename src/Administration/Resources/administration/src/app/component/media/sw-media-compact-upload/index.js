import template from './sw-media-compact-upload.html.twig';
import './sw-media-compact-upload.scss';

export default {
    name: 'sw-media-compact-upload',
    extendsFrom: 'sw-media-upload',
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
            type: String,
            required: false,
            default: ''
        }
    },

    methods: {
        onModalClosed(selection) {
            this.$emit('selectionChange', selection, this.uploadTag);
        }
    }
};

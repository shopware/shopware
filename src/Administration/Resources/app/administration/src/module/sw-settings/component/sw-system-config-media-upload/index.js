import template from './sw-system-config-media-upload.html.twig';

/**
 * @sw-package framework
 * @private
 */
export default {
    template,

    emits: ['update:value'],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },

        name: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            uploadTag: `sw-system-config-media-upload-${Shopware.Utils.createId()}`,
        };
    },

    methods: {
        successfulUpload({ targetId }) {
            this.$emit('update:value', targetId);
        },

        setMedia(selection) {
            this.$emit('update:value', selection.at(0)?.id ?? null);
        },

        removeMedia() {
            this.$emit('update:value', null);
        },
    },
};

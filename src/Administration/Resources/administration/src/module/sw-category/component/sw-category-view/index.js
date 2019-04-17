import { Component, Mixin } from 'src/core/shopware';
import template from './sw-category-view.html.twig';

Component.register('sw-category-view', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        category: {
            type: Object,
            required: true,
            default: {}
        },
        mediaItem: {
            type: Object,
            required: false,
            default: null
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    methods: {
        onUploadAdded({ uploadTag }) {
            this.$emit('sw-category-view-on-upload-media', uploadTag);
        },
        setMediaItem(mediaItem) {
            this.$emit('sw-category-view-on-set-media', mediaItem);
        },
        onUnlinkLogo() {
            this.$emit('sw-category-view-on-remove-media');
        },
        openSidebar() {
            this.$emit('sw-category-view-on-open-sidebar');
        }
    }
});

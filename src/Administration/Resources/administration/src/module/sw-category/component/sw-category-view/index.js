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
        cmsPage: {
            type: Object,
            required: false,
            default: null
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
            this.$emit('media-upload', uploadTag);
        },
        setMediaItem(mediaItem) {
            this.$emit('media-set', mediaItem);
        },
        onUnlinkLogo() {
            this.$emit('media-remove');
        },
        openSidebar() {
            this.$emit('sidebar-open');
        },
        onCmsLayoutChange(cmsPageId) {
            this.$emit('page-change', cmsPageId);
        }
    }
});

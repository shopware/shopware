import { Component } from 'src/core/shopware';
import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

Component.register('sw-cms-list-item', {
    template,

    props: {
        page: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        previewMedia() {
            if (!this.page.previewMedia || !this.page.previewMedia.id) {
                return null;
            }

            return {
                'background-image': `url(${this.page.previewMedia.url})`,
                'background-size': 'cover'
            };
        }
    },

    methods: {
        onChangePreviewImage(page) {
            this.$emit('preview-image-change', page);
        },

        onRemovePreviewImage(page) {
            page.previewMediaId = null;
            page.save();
            page.previewMedia = null;
        },

        onDelete(page) {
            this.$emit('cms-page-delete', page);
        }
    }
});

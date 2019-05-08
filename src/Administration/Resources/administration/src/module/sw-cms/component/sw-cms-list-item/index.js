import { Component, Application } from 'src/core/shopware';
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
            if (this.page.previewMedia && this.page.previewMedia.id && this.page.previewMedia.url) {
                return {
                    'background-image': `url(${this.page.previewMedia.url})`,
                    'background-size': 'cover'
                };
            }

            if (this.page.locked === true) {
                return {
                    'background-image': this.defaultLayoutAsset,
                    'background-size': 'cover'
                };
            }

            return null;
        },

        defaultLayoutAsset() {
            const initContainer = Application.getContainer('init');
            const context = initContainer.contextService;

            return `url(${context.assetsPath}/administration/static/img/cms/default_preview_${this.page.type}.jpg)`;
        }
    },

    methods: {
        onChangePreviewImage(page) {
            this.$emit('preview-image-change', page);
        },

        onElementClick() {
            this.$emit('onItemClick', this.page);
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

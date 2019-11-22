import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

const { Component } = Shopware;

Component.register('sw-cms-list-item', {
    template,

    props: {
        page: {
            type: Object,
            required: false,
            default: null
        },

        active: {
            type: Boolean,
            required: false,
            default: false
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
                    'background-image': this.defaultLayoutAsset
                };
            }

            if (this.defaultItemLayoutAssetBackground) {
                return {
                    'background-image': this.defaultItemLayoutAssetBackground,
                    'background-size': 'cover'
                };
            }

            return null;
        },

        defaultLayoutAsset() {
            const context = Shopware.Context.api;

            return `url(${context.assetsPath}/administration/static/img/cms/default_preview_${this.page.type}.jpg)`;
        },

        defaultItemLayoutAssetBackground() {
            const context = Shopware.Context.api;
            const path = 'administration/static/img/cms';

            if (this.page.sections.length < 1) {
                return null;
            }

            return `url(${context.assetsPath}/${path}/preview_${this.page.type}_${this.page.sections[0].type}.png)`;
        },

        componentClasses() {
            return {
                'is--active': this.isActive()
            };
        }
    },

    methods: {
        isActive() {
            return (this.page && this.page.categories && this.page.categories.length > 0) || this.active;
        },

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

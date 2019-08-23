import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

const { Component, Application } = Shopware;

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

            return null;
        },

        defaultLayoutAsset() {
            const initContainer = Application.getContainer('init');
            const context = initContainer.contextService;

            return `url(${context.assetsPath}/administration/static/img/cms/default_preview_${this.page.type}.jpg)`;
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

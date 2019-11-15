import template from './sw-theme-list-item.html.twig';
import './sw-theme-list-item.scss';

const { Component, Application } = Shopware;

Component.register('sw-theme-list-item', {
    template,

    props: {
        theme: {
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
            if (this.theme.previewMedia && this.theme.previewMedia.id && this.theme.previewMedia.url) {
                return {
                    'background-image': `url('${this.theme.previewMedia.url}')`,
                    'background-size': 'cover'
                };
            }

            return {
                'background-image': this.defaultThemeAsset
            };
        },

        defaultThemeAsset() {
            return `url('${Shopware.Context.api.assetsPath}/administration/static/img/theme/default_theme_preview.jpg')`;
        },

        lockToolTip() {
            return {
                showDelay: 100,
                message: this.$tc('sw-theme-manager.general.lockedToolTip')
            };
        },

        componentClasses() {
            return {
                'is--active': this.isActive()
            };
        }
    },

    methods: {
        isActive() {
            return this.theme && this.theme.salesChannels && this.theme.salesChannels.length > 0 || this.active;
        },

        onChangePreviewImage(theme) {
            this.$emit('preview-image-change', theme);
        },

        onThemeClick() {
            this.$emit('item-click', this.theme);
        },

        onRemovePreviewImage(theme) {
            theme.previewMediaId = null;
            theme.save();
            theme.previewMedia = null;
        },

        onDelete(theme) {
            this.$emit('theme-delete', theme);
        }
    }
});

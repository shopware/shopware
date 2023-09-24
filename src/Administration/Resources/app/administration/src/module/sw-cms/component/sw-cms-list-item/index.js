import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

const { Filter } = Shopware;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    props: {
        page: {
            type: Object,
            required: false,
            default: null,
        },

        active: {
            type: Boolean,
            required: false,
            default: false,
        },

        isDefault: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        previewMedia() {
            if (this.page.previewMedia?.id && this.page.previewMedia?.url) {
                return {
                    'background-image': `url(${this.page.previewMedia.url})`,
                    'background-size': 'cover',
                };
            }

            if (this.page.locked && this.page.type !== 'page') {
                return {
                    'background-image': this.defaultLayoutAsset,
                };
            }

            const backgroundImage = this.defaultItemLayoutAssetBackground;
            if (backgroundImage) {
                return {
                    'background-image': backgroundImage,
                    'background-size': 'cover',
                };
            }

            return null;
        },

        defaultLayoutAsset() {
            return `url(${this.assetFilter(`administration/static/img/cms/default_preview_${this.page.type}.jpg`)})`;
        },

        defaultItemLayoutAssetBackground() {
            const path = 'administration/static/img/cms';

            if (this.page.sections.length < 1) {
                return null;
            }

            return `url(${this.assetFilter(`${path}/preview_${this.page.type}_${this.page.sections[0].type}.png`)})`;
        },

        componentClasses() {
            return {
                'is--active': this.active,
                'is--disabled': this.disabled,
            };
        },

        statusClasses() {
            return {
                'is--active': this.active || this.isDefault,
            };
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    methods: {
        onChangePreviewImage(page) {
            this.$emit('preview-image-change', page);
        },

        /**
         * @deprecated tag:v6.6.0 - Will emit hypernated event only.
         */
        onElementClick() {
            if (this.disabled) {
                return;
            }

            this.$emit('onItemClick', this.page);
            this.$emit('on-item-click', this.page);
        },

        onItemClick(page) {
            if (this.disabled) {
                return;
            }

            this.$emit('item-click', page);
        },

        onRemovePreviewImage(page) {
            page.previewMediaId = null;
            page.save();
            page.previewMedia = null;
        },

        onDelete(page) {
            this.$emit('cms-page-delete', page);
        },
    },
};

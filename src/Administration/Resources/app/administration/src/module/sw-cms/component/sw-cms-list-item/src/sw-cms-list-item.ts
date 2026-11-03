import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';
import { featureService, getAssetFilter, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default wrapComponentConfig({
    template,

    inject: [featureService],

    emits: [
        'preview-image-change',
        'on-item-click',
        'element-click',
        'item-click',
        'cms-page-delete',
    ],

    props: {
        page: {
            type: Object as PropType<Entity<'cms_page'>>,
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
            return `url(${this.assetFilter(
                `administration/administration/static/img/cms/default_preview_${this.page.type}.webp`,
            )})`;
        },

        defaultItemLayoutAssetBackground() {
            const path = 'administration/administration/static/img/cms';

            if (this.page.sections!.length < 1) {
                return null;
            }

            return `url(${this.assetFilter(`${path}/preview_${this.page.type}_${this.page.sections![0].type}.png`)})`;
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
            return getAssetFilter();
        },
    },

    methods: {
        onChangePreviewImage(page: Entity<'cms_page'>) {
            this.$emit('preview-image-change', page);
        },

        onElementClick() {
            if (this.disabled) {
                return;
            }

            this.$emit('element-click', this.page);
        },

        onItemClick(page: Entity<'cms_page'>) {
            if (this.disabled) {
                return;
            }

            this.$emit('item-click', page);
        },

        onDelete(page: Entity<'cms_page'>) {
            this.$emit('cms-page-delete', page);
        },
    },
});

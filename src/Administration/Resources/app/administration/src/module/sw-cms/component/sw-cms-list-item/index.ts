import { type PropType } from 'vue';
import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

const { Filter } = Shopware;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    emits: [
        'preview-image-change',
        'on-item-click',
        'element-click',
        'item-click',
        'cms-page-delete',
    ],

    props: {
        page: {
            type: Object as PropType<EntitySchema.Entity<'cms_page'>>,
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
            return Filter.getByName('asset');
        },
    },

    methods: {
        onChangePreviewImage(page: EntitySchema.Entity<'cms_page'>) {
            this.$emit('preview-image-change', page);
        },

        /** @deprecated tag:v6.7.0 - `on-item-click` will be removed. Handle `element-click` instead */
        onElementClick() {
            if (this.disabled) {
                return;
            }

            this.$emit('on-item-click', this.page);
            this.$emit('element-click', this.page);
        },

        onItemClick(page: EntitySchema.Entity<'cms_page'>) {
            if (this.disabled) {
                return;
            }

            this.$emit('item-click', page);
        },

        /** @deprecated tag:v6.7.0 - `onRemovePreviewImage` will be removed without replacement */
        onRemovePreviewImage(page: EntitySchema.Entity<'cms_page'>) {
            page.previewMediaId = undefined;
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-explicit-any,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
            (page as any).save();
            page.previewMedia = undefined;
        },

        onDelete(page: EntitySchema.Entity<'cms_page'>) {
            this.$emit('cms-page-delete', page);
        },
    },
});

import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-cms-list-item',
    load: () => import('./src/sw-cms-list-item'),
    public: {
        props: [
            'page',
            'active',
            'isDefault',
            'disabled',
        ],
        emits: [
            'preview-image-change',
            'on-item-click',
            'element-click',
            'item-click',
            'cms-page-delete',
        ],
        slots: [
            'contextMenu',
        ],
        blocks: [
            'sw_cms_list_item',
            'sw_cms_list_item_options',
            'sw_cms_list_item_image',
            'sw_cms_list_item_info',
        ],
        computed: [
            'previewMedia',
            'defaultLayoutAsset',
            'defaultItemLayoutAssetBackground',
            'componentClasses',
            'statusClasses',
            'assetFilter',
        ],
        methods: [
            'onChangePreviewImage',
            'onElementClick',
            'onItemClick',
            'onDelete',
        ],
    },
});

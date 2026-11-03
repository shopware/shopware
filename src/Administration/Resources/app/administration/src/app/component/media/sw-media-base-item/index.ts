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
    name: 'sw-media-base-item',
    load: () => import('./src/sw-media-base-item'),
    public: {
        props: [
            'item',
            'isList',
            'showSelectionIndicator',
            'showContextMenuButton',
            'selected',
            'editable',
            'allowMultiSelect',
            'truncateRight',
            'allowEdit',
            'allowDelete',
            'disabled',
        ],
        emits: [
            'media-item-click',
            'media-item-selection-add',
            'media-item-selection-remove',
        ],
        slots: [
            'preview',
            'name',
            'metadata',
            'context-menu',
            'modal-windows',
        ],
        blocks: [
            'sw_media_base_item',
            'sw_media_base_item_selected_indicator',
            'sw_media_base_item_preview',
            'sw_media_base_item_slot_media_preview',
            'sw_media_base_spatial_label_indicator',
            'sw_media_base_item_name_container',
            'sw_media_base_item_metadata_container',
            'sw_media_base_item_metadata',
            'sw_media_base_item_slot_media_item_metadata',
            'sw_media_base_item_context_menu',
            'sw_media_base_item_context_items',
            'sw_media_base_item_slot_media_item_context_menu',
            'sw_media_base_item_list_selected_indicator',
            'sw_media_base_item_loading_indicator',
        ],
        computed: [
            'mediaItemClasses',
            'mediaNameContainerClasses',
            'listSelected',
            'selectionIndicatorClasses',
            'isLoading',
            'isSpatial',
        ],
        methods: [
            'createdComponent',
            'handleItemClick',
            'isSelectionIndicatorClicked',
            'onClickedItem',
            'selectItem',
            'removeFromSelection',
            'startInlineEdit',
            'endInlineEdit',
        ],
    },
});

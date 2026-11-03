import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-media-list-selection-item-v2',
    load: () => import('./src/sw-media-list-selection-item-v2'),
    public: {
        props: [
            'item',
            'hideActions',
            'hideTooltip',
            'disabled',
        ],
        emits: [
            'click',
            'item-remove',
        ],
        blocks: [
            'sw_media_list_selection_item_v2',
            'sw_media_list_selection_item_v2_preview',
            'sw_media_list_selection_item_v2_preview_media',
            'sw_media_list_selection_item_v2_preview_actions',
            'sw_media_list_selection_item_v2_context',
            'sw_media_list_selection_item_v2_context_delete_action',
            'sw_media_list_selection_item_v2_placeholder',
            'sw_media_list_selection_item_v2_placeholder_icon',
        ],
        computed: [
            'isPlaceholder',
            'productImageClasses',
            'sourceId',
        ],
    },
});

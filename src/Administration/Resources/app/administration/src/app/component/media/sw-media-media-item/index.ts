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
    name: 'sw-media-media-item',
    load: () => import('./src/sw-media-media-item'),
    public: {
        props: [
            'item',
        ],
        emits: [
            'media-item-rename-success',
            'media-item-play',
            'media-item-delete',
            'media-folder-move',
            'media-item-replaced',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_media_media_item',
            'sw_media_media_item_preview',
            'sw_media_media_item_name_container',
            'sw_media_media_item_metadata',
            'sw_media_media_item_context_menu',
            'sw_media_media_item_additional_context_menu_slot',
            'sw_media_media_item_context_group_quick_actions',
            'sw_media_media_item_context_item_rename_item',
            'sw_media_media_item_context_item_copy_item_link',
            'sw_media_media_item_context_item_replace',
            'sw_media_media_item_context_item_move',
            'sw_media_media_item_context_item_set_cover',
            'sw_media_media_item_context_item_remove_cover',
            'sw_media_media_item_context_item_delete',
            'sw_media_media_item_modal_replace',
            'sw_media_media_item_delete_modal',
            'sw_media_media_item_move_modal',
            'sw_media_media_item_cover_modal',
        ],
        computed: [
            'locale',
            'defaultContextMenuClass',
            'mediaNameFilter',
            'dateFilter',
            'fileSizeFilter',
            'extensionSdkButtons',
            'mediaRepository',
        ],
        methods: [
            'onChangeName',
            'handleErrorMessage',
            'rejectRenaming',
            'onBlur',
            'emitPlayEvent',
            'copyItemLink',
            'openModalDelete',
            'closeModalDelete',
            'emitItemDeleted',
            'openModalReplace',
            'closeModalReplace',
            'openModalMove',
            'closeModalMove',
            'onMediaItemMoved',
            'emitRefreshMediaLibrary',
            'runAppAction',
        ],
    },
});

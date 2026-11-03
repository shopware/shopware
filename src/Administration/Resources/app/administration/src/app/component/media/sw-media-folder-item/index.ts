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
    name: 'sw-media-folder-item',
    load: () => import('./src/sw-media-folder-item'),
    public: {
        props: [
            'isParent',
        ],
        emits: [
            'media-folder-remove',
            'media-folder-changed',
            'media-folder-delete',
            'media-folder-dissolve',
            'media-folder-move',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_media_folder_item',
            'sw_media_folder_item_preview',
            'sw_media_folder_parent_icon',
            'sw_media_folder_default_icon',
            'sw_media_folder_folder_icon',
            'sw_media_folder_name',
            'sw_media_base_item_name',
            'sw_media_folder_meta_data',
            'sw_media_folder_item_context_menu',
            'sw_media_folder_item_context_item_show_media',
            'sw_media_folder_item_additional_context_menu_slot',
            'sw_media_folder_item_context_group_quick_actions',
            'sw_media_folder_item_context_item_show_settings',
            'sw_media_folder_item_context_item_move',
            'sw_media_folder_item_context_item_dissolve',
            'sw_media_folder_item_context_item_rename_item',
            'sw_media_folder_item_context_item_delete',
            'sw_media_folder_modal_windows',
            'sw_media_folder_settings_modal',
            'sw_media_folder_dissolve_modal',
            'sw_media_folder_move_modal',
            'sw_media_folder_delete_modal',
        ],
        computed: [
            'mediaFolderRepository',
            'mediaDefaultFolderRepository',
            'moduleFactory',
            'mediaFolder',
            'iconName',
            'folderColor',
            'moduleIconColor',
            'assetFilter',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'getIconConfigFromFolder',
            'onChangeName',
            'onBlur',
            'rejectRenaming',
            'navigateToFolder',
            'openSettings',
            'closeSettings',
            'openDissolveModal',
            'closeDissolveModal',
            'openDeleteModal',
            'closeDeleteModal',
            'emitItemDeleted',
            'onFolderDissolved',
            'onFolderMoved',
            'openMoveModal',
            'closeMoveModal',
            'refreshIconConfig',
        ],
    },
});

import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-users-permissions',
    load: () => import('./src/sw-users-permissions'),
    public: {
        blocks: [
            'sw_users_permissions',
            'sw_users_permissions_smart_bar_header',
            'sw_users_permissions_smart_bar_header_title_text',
            'sw_users_permissions_smart_bar_actions',
            'sw_users_permissions_content',
            'sw_users_permissions_content_user_list',
        ],
        methods: [
            'reloadUserListing',
            'onChangeLoading',
            'onSave',
            'onSaveFinish',
        ],
    },
});

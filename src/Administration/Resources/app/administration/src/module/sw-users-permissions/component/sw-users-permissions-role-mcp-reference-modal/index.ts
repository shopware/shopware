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
    name: 'sw-users-permissions-role-mcp-reference-modal',
    load: () => import('./src/sw-users-permissions-role-mcp-reference-modal'),
    public: {
        props: [
            'role',
            'mcpIntegrations',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_users_permissions_role_mcp_reference_modal',
            'sw_users_permissions_role_mcp_reference_modal_content',
            'sw_users_permissions_role_mcp_reference_modal_save_reminder',
            'sw_users_permissions_role_mcp_reference_modal_toolbar',
            'sw_users_permissions_role_mcp_reference_modal_legend',
            'sw_users_permissions_role_mcp_reference_modal_loading',
            'sw_users_permissions_role_mcp_reference_modal_rows',
            'sw_users_permissions_role_mcp_reference_modal_row_label',
            'sw_users_permissions_role_mcp_reference_modal_row_chips',
            'sw_users_permissions_role_mcp_reference_modal_row_grant',
            'sw_users_permissions_role_mcp_reference_modal_empty',
            'sw_users_permissions_role_mcp_reference_modal_footer',
        ],
        computed: [
            'filterOptions',
            'allowlistedToolNames',
            'relevantTools',
            'rolePrivileges',
            'displayRows',
            'allMissingStatic',
        ],
        methods: [
            'getBadgeVariant',
            'loadTools',
            'grantPrivilege',
            'grantAllMissing',
            'grantRow',
            'closeModal',
        ],
    },
});

import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-permissions-details-modal',
    load: () => import('./src/sw-extension-permissions-details-modal'),
    public: {
        props: [
            'permissions',
            'modalTitle',
            'selectedEntity',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_extension_permissions_details_modal',
            'sw_extension_permissions_details_modal_content',
            'sw_extension_permissions_details_modal_table',
            'sw_extension_permissions_details_modal_table_head',
            'sw_extension_permissions_details_modal_table_head_row',
            'sw_extension_permissions_details_modal_table_body',
            'sw_extension_permissions_details_modal_table_permissions',
            'sw_extension_permissions_details_modal_permissions_category',
            'sw_extension_permissions_details_modal_permission_row',
            'sw_extension_permissions_details_modal_permission_entity',
            'sw_extension_permissions_details_modal_permission_operation',
            'sw_extension_permissions_details_modal_footer',
            'sw_extension_permissions_details_modal_footer_inner',
        ],
        computed: [
            'operations',
            'ankerId',
        ],
        methods: [
            'scrollSelectedEntityIntoView',
            'close',
            'categoryLabel',
            'entityLabel',
        ],
    },
});

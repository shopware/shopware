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
    name: 'sw-extension-permissions-modal',
    load: () => import('./src/sw-extension-permissions-modal'),
    public: {
        props: [
            'permissions',
            'domains',
            'extensionLabel',
            'actionLabel',
            'closeLabel',
            'title',
            'description',
        ],
        emits: [
            'modal-close',
            'close-with-action',
        ],
        blocks: [
            'sw_extension_permissions_modal',
            'sw_extension_permissions_modal_content',
            'sw_extension_permissions_modal_intro',
            'sw_extension_permissions_modal_intro_image',
            'sw_extension_permissions_modal_intro_text',
            'sw_extension_permissions_modal_categories',
            'sw_extension_permissions_modal_category',
            'sw_extension_permissions_modal_category_inner',
            'sw_extension_permissions_modal_category_label',
            'sw_extension_permissions_modal_link',
            'sw_extension_permissions_modal_category_domains',
            'sw_extension_permissions_modal_footer',
            'sw_extension_permissions_modal_footer_inner',
            'sw_extension_permissions_modal_footer_close',
            'sw_extension_permissions_modal_footer_action',
            'sw_extension_permissions_modal_details',
            'sw_extension_permissions_modal_domains',
        ],
        computed: [
            'modalTitle',
            'permissionsWithGroupedOperations',
            'domainsList',
            'closeBtnLabel',
            'descriptionText',
            'assetFilter',
        ],
        methods: [
            'close',
            'closeWithAction',
            'categoryLabel',
            'openDetailsModal',
            'closeDetailsModal',
            'toggleDomainsModal',
        ],
    },
});

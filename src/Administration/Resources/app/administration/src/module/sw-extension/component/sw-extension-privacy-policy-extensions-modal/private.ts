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
    name: 'sw-extension-privacy-policy-extensions-modal',
    load: () => import('./src/sw-extension-privacy-policy-extensions-modal'),
    public: {
        props: [
            'extensionName',
            'privacyPolicyExtension',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_extension_privacy_policy_extensions_modal',
            'sw_extension_privacy_policy_extensions_modal_content',
            'sw_extension_privacy_policy_extensions_modal_intro',
            'sw_extension_privacy_policy_extensions_modal_text',
            'sw_extension_permissions_modal_footer',
            'sw_extension_permissions_modal_footer_inner',
            'sw_extension_permissions_modal_footer_close',
        ],
        computed: [
            'title',
        ],
        methods: [
            'close',
        ],
    },
});

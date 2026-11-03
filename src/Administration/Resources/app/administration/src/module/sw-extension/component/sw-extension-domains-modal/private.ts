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
    name: 'sw-extension-domains-modal',
    load: () => import('./src/sw-extension-domains-modal'),
    public: {
        props: [
            'extensionLabel',
            'domains',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_extension_domains_modal',
            'sw_extension_domains_modal_description',
            'sw_extension_domains_modal_list_title',
            'sw_extension_domains_modal_list',
            'sw_extension_domains_modal_list_entry',
            'sw_extension_domains_modal_footer',
            'sw_extension_domains_modal_footer_inner',
        ],
        computed: [
            'modalTitle',
        ],
        methods: [
            'close',
        ],
    },
});

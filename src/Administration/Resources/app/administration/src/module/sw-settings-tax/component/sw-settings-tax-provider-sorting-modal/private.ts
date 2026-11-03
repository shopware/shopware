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
    name: 'sw-settings-tax-provider-sorting-modal',
    load: () => import('./src/sw-settings-tax-provider-sorting-modal'),
    public: {
        props: [
            'taxProviders',
        ],
        emits: [
            'modal-close',
            'modal-save',
        ],
        blocks: [
            'sw_settings_tax_provider_sorting_modal',
        ],
        computed: [
            'taxProviderRepository',
        ],
        methods: [
            'closeModal',
            'applyChanges',
            'onSort',
        ],
    },
});

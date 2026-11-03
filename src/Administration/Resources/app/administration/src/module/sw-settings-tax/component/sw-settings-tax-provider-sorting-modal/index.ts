import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
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

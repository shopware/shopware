import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-first-run-wizard-paypal-info',
    load: () => import('./src/sw-first-run-wizard-paypal-info'),
    public: {
        emits: [
            'frw-set-title',
            'buttons-update',
        ],
        blocks: [
            'sw_first_run_wizard_paypal_info',
        ],
        computed: [
            'assetFilter',
        ],
        methods: [
            '_reloadPage',
            'createdComponent',
            'setTitle',
            'updateButtons',
            'installPayPal',
            'activatePayPalAndRedirect',
        ],
    },
});

import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-first-run-wizard-defaults',
    load: () => import('./src/sw-first-run-wizard-defaults'),
    public: {
        emits: [
            'frw-set-title',
            'frw-redirect',
            'buttons-update',
        ],
        blocks: [
            'sw_first_run_wizard_defaults',
            'sw_first_run_wizard_defaults_sales_channel_card',
        ],
        computed: [
            'salesChannelRepository',
            'buttonConfig',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'nextAction',
            'updateButtons',
            'updateSalesChannel',
        ],
    },
});

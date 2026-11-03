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
    name: 'sw-first-run-wizard-mailer-base',
    load: () => import('./src/sw-first-run-wizard-mailer-base'),
    public: {
        blocks: [
            'sw_first_run_wizard_mailer_base',
        ],
        computed: [
            'filteredAttributes',
        ],
    },
});

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
    name: 'sw-first-run-wizard-mailer-smtp',
    load: () => import('./src/sw-first-run-wizard-mailer-smtp'),
    public: {
        emits: [
            'buttons-update',
            'frw-set-title',
            'frw-redirect',
        ],
        blocks: [
            'sw_first_run_wizard_mailer_smtp',
        ],
        computed: [
            'nextAction',
            'buttonConfig',
            'requiredFieldsFilled',
        ],
        methods: [
            'createdComponent',
            'updateButtons',
            'setTitle',
            'loadMailerSettings',
            'saveMailerSettings',
        ],
    },
});

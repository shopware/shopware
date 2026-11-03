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
    name: 'sw-first-run-wizard-shopware-domain',
    load: () => import('./src/sw-first-run-wizard-shopware-domain'),
    public: {
        emits: [
            'frw-set-title',
            'buttons-update',
            'frw-redirect',
        ],
        blocks: [
            'sw_first_run_wizard_shopware_domain',
        ],
        computed: [
            'domainToVerify',
            'isDomainEmpty',
            'nextAction',
            'domainOptions',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
            'verifyDomain',
        ],
    },
});

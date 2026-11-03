import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component for the module around it, which mounts it locally: it has no global name and never reaches extensions.
 * The lists hold what the parent uses. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-services-revoke-permissions-modal',
    load: () => import('./src/sw-settings-services-revoke-permissions-modal'),
    public: {
        props: [
            'servicesWithAccountRequirement',
        ],
        emits: [
            'service-permissions-revoked',
        ],
    },
});

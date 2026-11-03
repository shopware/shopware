import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The card shows one Shopware service on the services settings page and lets a merchant activate it, deactivate it or read its permissions.
 * The services page mounts it locally: it has no global name and never reaches extensions.
 * The lists hold what the page uses. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-services-service-card',
    load: () => import('./src/sw-settings-services-service-card'),
    public: {
        props: [
            'service',
        ],
    },
});

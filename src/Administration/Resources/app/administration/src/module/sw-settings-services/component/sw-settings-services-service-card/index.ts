import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The card shows one Shopware service on the services settings page and lets a merchant activate it, deactivate it or read its permissions.
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The component has no global name: its parent imports it through private.ts and mounts it locally,
 * so Component.override cannot reach it. The lists hold what a parent uses. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-services-service-card',
    load: () => import('./src/sw-settings-services-service-card'),
    public: {
        props: [
            'service',
        ],
    },
});

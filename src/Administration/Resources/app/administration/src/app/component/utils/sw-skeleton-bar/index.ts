import template from './sw-skeleton-bar.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-skeleton-bar and mt-skeleton-bar. Autoswitches between the two components.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            return false;
        },
    },
});

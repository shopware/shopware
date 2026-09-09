import template from './sw-skeleton-bar.html.twig';
import { debug } from 'shopware:utils';

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

            // Throw warning when deprecated component is used
            debug.warn(
                'sw-skeleton-bar',
                'The old usage of "sw-skeleton-bar" is deprecated and will be removed in v6.8.0.0. Please use "mt-skeleton-bar" instead.',
            );

            return false;
        },
    },
});

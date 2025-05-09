import template from './sw-alert.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-alert and mt-banner. Switches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-banner instead
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    props: {
        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
});

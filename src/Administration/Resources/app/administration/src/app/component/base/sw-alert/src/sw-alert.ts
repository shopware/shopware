import template from './sw-alert.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-alert and mt-banner. Switches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-banner instead
 */
export default wrapComponentConfig({
    template,
    props: {
        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
});

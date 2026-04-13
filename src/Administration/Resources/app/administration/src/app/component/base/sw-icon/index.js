import template from './sw-icon.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-icon and mt-icon. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-icon instead.
 */
export default {
    template,

    props: {
        name: {
            type: String,
            required: true,
        },

        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};

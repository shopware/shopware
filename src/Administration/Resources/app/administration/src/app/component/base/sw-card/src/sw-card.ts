import template from './sw-card.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-card and mt-card. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-card instead.
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

    methods: {
        getSlots() {
            return this.$slots;
        },
    },
});

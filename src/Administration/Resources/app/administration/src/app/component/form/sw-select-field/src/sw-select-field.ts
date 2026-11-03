import template from './sw-select-field.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-select-field and mt-select. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-select instead.
 */
export default wrapComponentConfig({
    template,

    props: {
        options: {
            type: Array,
            required: false,
        },

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

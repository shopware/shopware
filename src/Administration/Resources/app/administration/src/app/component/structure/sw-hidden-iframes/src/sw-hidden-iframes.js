import template from './sw-hidden-iframes.html.twig';
import { MAIN_HIDDEN, useExtensionsStore } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        extensions() {
            return useExtensionsStore().privilegedExtensions;
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
};

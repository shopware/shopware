import { MAIN_HIDDEN } from '@shopware-ag/meteor-admin-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        extensions() {
            return Shopware.Store.get('extensions').privilegedExtensions;
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
};

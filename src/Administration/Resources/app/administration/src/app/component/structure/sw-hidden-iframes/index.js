import { MAIN_HIDDEN } from '@shopware-ag/meteor-admin-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';
import useExtensionsStore from 'shopware:stores/extensions';

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

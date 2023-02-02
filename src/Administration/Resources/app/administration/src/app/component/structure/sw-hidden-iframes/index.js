import { MAIN_HIDDEN } from '@shopware-ag/admin-extension-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-hidden-iframes', {
    template,

    computed: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed use extensions instead.
         */
        iFrames() {
            return Shopware.State.getters['extensions/privilegedExtensionBaseUrls'];
        },

        extensions() {
            return Shopware.State.getters['extensions/privilegedExtensions'];
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
});

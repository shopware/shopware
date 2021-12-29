import { MAIN_HIDDEN } from '@shopware-ag/admin-extension-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-hidden-iframes', {
    template,

    computed: {
        iFrames() {
            return Shopware.State.getters['extensions/allBaseUrls'];
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
});

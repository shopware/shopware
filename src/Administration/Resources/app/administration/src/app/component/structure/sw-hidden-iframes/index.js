import { MAIN_HIDDEN } from '@shopware-ag/admin-extension-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-hidden-iframes', {
    template,

    computed: {
        extensions() {
            return Shopware.State.getters['extensions/privilegedExtensions'];
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
});

import { MAIN_HIDDEN } from '@shopware-ag/meteor-admin-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 */
Component.register('sw-hidden-iframes', {
    template,

    computed: {
        extensions() {
            return Shopware.Store.get('extensions').privilegedExtensions;
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
});

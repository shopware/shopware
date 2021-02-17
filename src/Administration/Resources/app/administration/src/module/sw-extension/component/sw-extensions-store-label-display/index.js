import template from './sw-extension-store-label-display.html.twig';
import './sw-extension-store-label-display.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extensions-store-label-display', {
    template,

    props: {
        labels: {
            type: Array,
            required: true
        }
    }

});

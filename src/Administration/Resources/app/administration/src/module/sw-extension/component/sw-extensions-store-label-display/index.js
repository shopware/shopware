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
    },

    methods: {
        determineTextColor(backgroudColor) {
            if (!backgroudColor) {
                return '#000';
            }

            const hexColor = backgroudColor.charAt(0) === '#' ? backgroudColor.substring(1, 7) : backgroudColor;

            const r = parseInt(hexColor.substring(0, 2), 16); // hexToR
            const g = parseInt(hexColor.substring(2, 4), 16); // hexToG
            const b = parseInt(hexColor.substring(4, 6), 16); // hexToB

            return (r * 0.299 + g * 0.587 + b * 0.114) > 186 ? '#000' : '#fff';
        }
    }
});

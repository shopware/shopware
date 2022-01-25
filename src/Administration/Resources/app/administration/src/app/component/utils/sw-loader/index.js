import './sw-loader.scss';
import template from './sw-loader.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Renders a loading indicator for panels, input fields, buttons, etc.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-loader />
 */
Component.register('sw-loader', {
    template,

    props: {
        /**
         * @deprecated tag:v6.5.0 - Will be validated to be a px value greater or equal to 12px.
         */
        size: {
            type: String,
            required: false,
            default: '50px',
            /* validator(value) {
                return /^(12|[2-9][0-9]|[1-9][2-9]|[1-9]\d{2,})px$/.test(value);
            }, */
        },
    },

    computed: {
        loaderSize() {
            return {
                width: `${this.numericSize}px`,
                height: `${this.numericSize}px`,
            };
        },

        numericSize() {
            const numericSize = parseInt(this.size, 10);

            if (Number.isNaN(numericSize)) {
                return 50;
            }

            if (numericSize < 12) {
                return 50;
            }

            return numericSize;
        },

        borderWidth() {
            return `${Math.floor(this.numericSize / 12)}px`;
        },
    },
});

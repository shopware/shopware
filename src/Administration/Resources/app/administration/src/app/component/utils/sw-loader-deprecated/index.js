import './sw-loader.scss';
import template from './sw-loader.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Renders a loading indicator for panels, input fields, buttons, etc.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-loader />
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-loader-deprecated', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        size: {
            type: String,
            required: false,
            default: '50px',
            validator(value) {
                return /^(12|[2-9][0-9]|[1-9][2-9]|[1-9]\d{2,})px$/.test(value);
            },
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

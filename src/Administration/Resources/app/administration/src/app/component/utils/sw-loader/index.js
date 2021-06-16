import './sw-loader.scss';
import template from './sw-loader.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Renders a loading indicator for panels, input fields, buttons, etc.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-loader></sw-loader>
 */
Component.register('sw-loader', {
    template,

    props: {
        size: {
            type: String,
            required: false,
            default: '50px',
        },
    },

    computed: {
        loaderSize() {
            return {
                width: this.size,
                height: this.size,
            };
        },
    },
});

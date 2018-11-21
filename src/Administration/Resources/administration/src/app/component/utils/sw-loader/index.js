import { Component } from 'src/core/shopware';
import './sw-loader.less';
import template from './sw-loader.html.twig';

/**
 * @public
 * @description Renders a loading indicator for panels, input fields, buttons, usw.
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
            default: '50px'
        }
    },

    computed: {
        loaderSize() {
            return {
                width: this.size,
                height: this.size
            };
        }
    }
});

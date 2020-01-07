import template from './sw-popover.html.twig';
import './sw-popover.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Renders a popover
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-popover></sw-popover>
 */
Component.register('sw-popover', {
    template,

    props: {
        zIndex: {
            type: [Number, null],
            required: false,
            default: null
        },
        resizeWidth: {
            type: Boolean,
            required: false,
            default: false
        },
        popoverClass: {
            type: String,
            required: false,
            default: ''
        }

    },

    computed: {
        componentStyle() {
            return {
                'z-Index': this.zIndex
            };
        },
        popoverConfig() {
            return {
                active: true,
                resizeWidth: this.resizeWidth
            };
        }
    }
});

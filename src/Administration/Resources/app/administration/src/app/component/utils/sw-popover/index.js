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
        },
        /**
         * @deprecated tag:v6.2.0
         */
        isPopover: {
            type: Boolean,
            required: false,
            default: true,
            validator() {
                Shopware.Utils.debug.warn(
                    'sw-popover',
                    'The property "isPopover" is deprecated and will be removed in 6.2'
                );
                return true;
            }
        },
        /**
         * @deprecated tag:v6.3.0
         */
        popoverConfigExtension: {
            type: Object,
            required: false,
            default() {
                return {};
            },
            validator() {
                Shopware.Utils.debug.warn(
                    'sw-popover',
                    'The property "popoverConfigExtension" is deprecated and will be removed in 6.3'
                );
                return true;
            }
        }

    },

    computed: {
        componentStyle() {
            return {
                'z-Index': this.zIndex
            };
        },
        popoverConfig() {
            const popoverConfigBase = this.popoverConfigExtension || {};

            return {
                ...popoverConfigBase,
                active: true,
                resizeWidth: this.resizeWidth
            };
        }
    }
});

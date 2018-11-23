import { Component } from 'src/core/shopware';
import './sw-button.less';
import template from './sw-button.html.twig';

/**
 * @status ready
 * @description The <u>sw-button</u> component replaces the standard html button or anchor element with a custom button
 * and a multitude of options.
 * @example-type dynamic
 * @component-example
 * <sw-button>
 *     Button
 * </sw-button>
 */
Component.register('sw-button', {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        variant: {
            type: String,
            required: false,
            default: '',
            validValues: ['primary', 'ghost', 'danger', 'contrast'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['primary', 'ghost', 'danger', 'contrast'].includes(value);
            }
        },
        size: {
            type: String,
            required: false,
            default: '',
            validValues: ['small', 'large'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['small', 'large'].includes(value);
            }
        },
        block: {
            type: Boolean,
            required: false,
            default: false
        },
        routerLink: {
            type: Object,
            required: false
        },
        link: {
            type: String,
            required: false
        }
    },

    computed: {
        buttonClasses() {
            return {
                [`sw-button--${this.variant}`]: this.variant,
                [`sw-button--${this.size}`]: this.size,
                'sw-button--block': this.block,
                'sw-button--disabled': this.disabled
            };
        }
    }
});

import './sw-button.scss';
import template from './sw-button.html.twig';

const { Component } = Shopware;

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
            validValues: ['x-small', 'small', 'large'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['x-small', 'small', 'large'].includes(value);
            }
        },
        square: {
            type: Boolean,
            required: false,
            default: false
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
        },
        isLoading: {
            type: Boolean,
            default: false,
            required: false
        }
    },

    computed: {
        buttonClasses() {
            return {
                [`sw-button--${this.variant}`]: this.variant,
                [`sw-button--${this.size}`]: this.size,
                'sw-button--block': this.block,
                'sw-button--disabled': this.disabled,
                'sw-button--square': this.square
            };
        },

        contentVisibilityClass() {
            return {
                'is--hidden': this.isLoading
            };
        }
    }
});

import './sw-button.scss';
import template from './sw-button.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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
            default: false,
        },
        variant: {
            type: String,
            required: false,
            default: '',
            validValues: ['primary', 'ghost', 'danger', 'ghost-danger', 'contrast', 'context'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['primary', 'ghost', 'danger', 'ghost-danger', 'contrast', 'context'].includes(value);
            },
        },
        size: {
            type: String,
            required: false,
            default: '',
            /**
             * @deprecated tag:v6.6.0 - "large" value will be removed
             */
            validValues: ['x-small', 'small', 'large'],
            validator(value) {
                if (!value.length) {
                    return true;
                }

                /**
                 * @deprecated tag:v6.6.0 - "large" value will be removed
                 */
                return ['x-small', 'small', 'large'].includes(value);
            },
        },
        square: {
            type: Boolean,
            required: false,
            default: false,
        },
        block: {
            type: Boolean,
            required: false,
            default: false,
        },
        // FIXME: add required flag
        // eslint-disable-next-line vue/require-default-prop
        routerLink: {
            type: Object,
            required: false,
        },
        link: {
            type: String,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    computed: {
        buttonClasses() {
            return {
                [`sw-button--${this.variant}`]: this.variant,
                [`sw-button--${this.size}`]: this.size,
                'sw-button--block': this.block,
                'sw-button--disabled': this.disabled,
                'sw-button--square': this.square,
            };
        },

        contentVisibilityClass() {
            return {
                'is--hidden': this.isLoading,
            };
        },
    },
});

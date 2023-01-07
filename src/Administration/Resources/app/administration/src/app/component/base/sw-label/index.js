import './sw-label.scss';
import template from './sw-label.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-label>
 *     Text
 * </sw-label>
 */
Component.register('sw-label', {
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: '',
            validValues: ['info', 'danger', 'success', 'warning', 'neutral', 'primary'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['info', 'danger', 'success', 'warning', 'neutral', 'primary'].includes(value);
            },
        },
        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['small', 'medium', 'default'],
            validator(value) {
                return ['small', 'medium', 'default'].includes(value);
            },
        },
        appearance: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'pill', 'circle', 'badged'],
            validator(value) {
                return ['default', 'pill', 'circle', 'badged'].includes(value);
            },
        },
        ghost: {
            type: Boolean,
            required: false,
            default: false,
        },
        caps: {
            type: Boolean,
            required: false,
            default: false,
        },
        dismissable: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        labelClasses() {
            return [
                `sw-label--appearance-${this.appearance}`,
                `sw-label--size-${this.size}`,
                {
                    [`sw-label--${this.variant}`]: this.variant,
                    'sw-label--dismissable': this.showDismissable,
                    'sw-label--ghost': this.ghost,
                    'sw-label--caps': this.caps,
                    'sw-label--light': this.light,
                },
            ];
        },
        showDismissable() {
            return !!this.$listeners.dismiss && this.dismissable;
        },
    },
});

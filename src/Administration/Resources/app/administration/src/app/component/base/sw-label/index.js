import './sw-label.scss';
import template from './sw-label.html.twig';

const { Component } = Shopware;

/**
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
            validValues: ['default', 'pill', 'circle'],
            validator(value) {
                return ['default', 'pill', 'circle'].includes(value);
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

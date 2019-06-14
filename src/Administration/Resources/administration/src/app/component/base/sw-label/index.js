import './sw-label.scss';
import template from './sw-label.html.twig';

/**
 * @public
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-label>
 *     Text
 * </sw-label>
 */
export default {
    name: 'sw-label',
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: '',
            validValues: ['info', 'danger', 'success', 'warning', 'neutral'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['info', 'danger', 'success', 'warning', 'neutral'].includes(value);
            }
        },
        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['small', 'medium', 'default'],
            validator(value) {
                return ['small', 'medium', 'default'].includes(value);
            }
        },
        appearance: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'pill'],
            validator(value) {
                return ['default', 'pill'].includes(value);
            }
        },
        ghost: {
            type: Boolean,
            required: false,
            default: false
        },
        caps: {
            type: Boolean,
            required: false,
            default: false
        }
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
                    'sw-label--light': this.light
                }
            ];
        },
        showDismissable() {
            return !!this.$listeners.dismiss;
        }
    }
};

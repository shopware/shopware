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
            validValues: ['info', 'danger', 'success', 'warning'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['info', 'danger', 'success', 'warning'].includes(value);
            }
        },
        dismissAble: {
            type: Boolean,
            required: false,
            default: false
        },
        pill: {
            type: Boolean,
            required: false,
            default: false
        },
        ghost: {
            type: Boolean,
            required: false,
            default: false
        },
        circle: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        labelClasses() {
            return {
                [`sw-label--${this.variant}`]: this.variant,
                'sw-label--dismiss-able': this.dismissAble,
                'sw-label--pill': this.pill,
                'sw-label--ghost': this.ghost,
                'sw-label--circle': this.circle
            };
        }
    }
};

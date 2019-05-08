import checkbox from '../sw-checkbox-field/index';
import template from './sw-switch-field.html.twig';
import './sw-switch-field.scss';

/**
 * @public
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-switch-field label="Name" v-model="aBooleanProperty"></sw-switch-field>
 */
export default {
    name: 'sw-switch-field',
    extends: checkbox,
    template,
    inheritAttrs: false,

    props: {
        bordered: {
            type: Boolean,
            required: false,
            default: false
        },

        noMarginTop: {
            type: Boolean,
            required: false,
            default: false
        },

        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['small', 'medium', 'default'],
            validator(val) {
                return ['small', 'medium', 'default'].includes(val);
            }
        }
    },

    computed: {
        swSwitchFieldClasses() {
            return [
                {
                    'sw-field--switch-bordered': this.bordered,
                    'sw-field--switch-no-margin-top': this.noMarginTop,
                    ...this.swCheckboxFieldClasses
                },
                `sw-field--${this.size}`
            ];
        }
    }
};

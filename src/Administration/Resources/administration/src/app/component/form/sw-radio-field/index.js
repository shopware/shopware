import { Mixin } from 'src/core/shopware';
import template from './sw-radio-field.html.twig';
import './sw-radio-field.scss';

/**
 * @public
 * @description radio input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-radio-field label="Radio field example" :options="[
 *     {'value': 'value1', 'name': 'Label #1'},
 *     {'value': 'value2', 'name': 'Label #2'},
 *     {'value': 'value3', 'name': 'Label #3'},
 *     {'value': 'value4', 'name': 'Label #4'},
 *     {'value': 'value5', 'name': 'Label #5'}
 * ]"></sw-radio-field>
 */
export default {
    name: 'sw-radio-field',
    template,
    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change'
    },

    mixins: [
        Mixin.getByName('sw-form-field')
    ],

    props: {
        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            }
        },
        value: {
            required: false
        }
    },

    data() {
        return {
            currentValue: this.value
        };
    },

    watch: {
        value() { this.currentValue = this.value; }
    },

    methods: {
        onChange(event) {
            this.$emit('change', event.target.value);
        }
    }
};

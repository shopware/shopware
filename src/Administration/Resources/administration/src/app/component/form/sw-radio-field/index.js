import template from './sw-radio-field.html.twig';

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
    extendsFrom: 'sw-text-field',
    template,

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

    computed: {
        typeFieldClass() {
            return 'sw-field--radio';
        }
    },

    methods: {
        onChange() {
            this.$emit('input', this.currentValue);
            this.$emit('change', this.currentValue);

            if (this.hasError) {
                this.errorStore.deleteError(this.formError);
            }
        }
    }
};

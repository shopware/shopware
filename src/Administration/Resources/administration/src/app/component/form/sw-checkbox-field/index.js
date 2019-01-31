import template from './sw-checkbox-field.html.twig';

/**
 * @public
 * @description checkbox input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-checkbox-field type="checkbox" :label="Name" :placeholder="placeholder goes here..."
 * v-model="model"></sw-checkbox-field>
 */
export default {
    name: 'sw-checkbox-field',
    extendsFrom: 'sw-text-field',
    template,

    computed: {
        typeFieldClass() {
            return 'sw-field--checkbox';
        }
    },
    methods: {
        onChange(event) {
            const checkedAttribute = event.target.checked;

            this.$emit('input', checkedAttribute);
            this.$emit('change', checkedAttribute);
        }
    }
};

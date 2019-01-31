import template from './sw-boolean-field.html.twig';

/**
 * @public
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-boolean-field :label="Name" :placeholder="placeholder goes here..." v-model="model"></sw-boolean-field>
 */
export default {
    name: 'sw-boolean-field',
    extendsFrom: 'sw-text-field',
    template,

    computed: {
        typeFieldClass() {
            return 'sw-field--boolean';
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

import template from './sw-checkbox-field.html.twig';

/**
 * @public
 * @description Checkbox input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-checkbox-field type="checkbox" label="Name" placeholder="placeholder goes here..."></sw-checkbox-field>
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
            const checkedCustomField = event.target.checked;

            this.$emit('input', checkedCustomField);
            this.$emit('change', checkedCustomField);
        }
    }
};

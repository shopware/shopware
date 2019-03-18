import template from './sw-switch-field.html.twig';

/**
 * @public
 * @description switch input field based on type checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-switch-field label="Name" placeholder="placeholder goes here..."></sw-switch-field>
 */
export default {
    name: 'sw-switch-field',
    extendsFrom: 'sw-text-field',
    template,

    props: {
        value: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    model: {
        event: 'change'
    },

    computed: {
        typeFieldClass() {
            return 'sw-field--switch';
        }
    },

    methods: {
        onChange(event) {
            const checkedAttribute = event.target.checked;

            this.$emit('change', checkedAttribute);
        }
    }
};

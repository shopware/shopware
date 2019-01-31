import template from './sw-radio-field.html.twig';

/**
 * @public
 * @description radio input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-radio-field :label="Name" v-model="model" :options=[{'value': 'value1', 'name': 'testlabel'}]></sw-radio-field>
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

            if (this.hasError) {
                this.errorStore.deleteError(this.formError);
            }
        }
    }
};

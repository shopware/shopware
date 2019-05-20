export default {
    name: 'sw-boolean-radio-group',

    model: {
        prop: 'value',
        event: 'change'
    },

    template:
`
<sw-radio-field
    v-bind="$attrs"
    :options="options"
    v-model="castedValue">
</sw-radio-field>
`,

    props: {
        value: {
            type: Boolean,
            required: true
        },

        labelOptionTrue: {
            type: String,
            required: true
        },

        labelOptionFalse: {
            type: String,
            required: true
        }
    },

    computed: {
        options() {
            return [
                { value: 'true', name: this.labelOptionTrue },
                { value: 'false', name: this.labelOptionFalse }
            ];
        },

        castedValue: {
            get() {
                return this.value.toString();
            },

            set(val) {
                this.$emit('change', val === 'true');
            }
        }
    }
};

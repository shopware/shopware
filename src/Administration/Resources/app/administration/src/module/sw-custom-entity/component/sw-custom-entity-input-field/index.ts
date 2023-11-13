import type { PropType } from 'vue';
import template from './sw-custom-entity-input-field.html.twig';

/**
 * @private
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['feature'],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: [Object, String, Number, Boolean] as PropType<unknown>,
            required: false,
            default: null,
        },

        type: {
            type: String,
            required: true,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        helpText: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        currentValue: {
            get(): string | number | unknown {
                return this.value;
            },

            set(newValue: string | number): void {
                this.onChange(newValue);
            },
        },
    },

    methods: {
        onChange(eventInput: string | number): void {
            if (this.feature.isActive('VUE3')) {
                this.$emit('update:value', eventInput);

                return;
            }

            this.$emit('change', eventInput);
        },
    },
});

import type { PropType } from 'vue';
import template from './sw-custom-entity-input-field.html.twig';

/**
 * @private
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

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
            // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
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
            this.$emit('update:value', eventInput);
            return;

            this.$emit('change', eventInput);
        },
    },
});

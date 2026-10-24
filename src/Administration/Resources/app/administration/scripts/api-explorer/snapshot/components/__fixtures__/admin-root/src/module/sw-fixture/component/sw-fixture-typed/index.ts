import type { PropType } from 'vue';
import template from '../shared/sw-fixture-typed.twig';

/**
 * @sw-package framework
 * @experimental
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: {
        'update:modelValue': (value: string) => typeof value === 'string',
    },

    props: {
        modelValue: {
            type: String,
            required: true,
        },
        size: {
            type: String as PropType<'small' | 'large'>,
            default: 'small',
        },
    },

    data(): {
        selected: string | null;
        open: boolean;
        layout: { align: 'start' | 'end' };
        settings: {
            firstOptionWithAVeryLongName: string;
            secondOptionWithAVeryLongName: string;
            thirdOptionWithAVeryLongName: string;
            fourthOptionWithAVeryLongName: string;
            fifthOptionWithAVeryLongName: string;
            sixthOptionWithAVeryLongName: string;
            seventhOptionWithAVeryLongName: string;
            eighthOptionWithAVeryLongName: string;
            lastOption: 'first';
        };
    } {
        return {
            selected: null,
            open: false,
            layout: { align: 'start' },
            settings: {
                firstOptionWithAVeryLongName: '',
                secondOptionWithAVeryLongName: '',
                thirdOptionWithAVeryLongName: '',
                fourthOptionWithAVeryLongName: '',
                fifthOptionWithAVeryLongName: '',
                sixthOptionWithAVeryLongName: '',
                seventhOptionWithAVeryLongName: '',
                eighthOptionWithAVeryLongName: '',
                lastOption: 'first',
            },
        };
    },

    computed: {
        label(): string {
            return this.modelValue.toUpperCase();
        },

        current: {
            get(): string | null {
                return this.selected;
            },
            set(value: string | null) {
                this.selected = value;
            },
        },
    },

    methods: {
        select(value: string, silent?: boolean): void {
            this.selected = value;

            if (!silent) {
                this.$emit('update:modelValue', value);
            }
        },
    },
});

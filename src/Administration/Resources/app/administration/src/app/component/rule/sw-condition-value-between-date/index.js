import template from './sw-condition-value-between-date.html.twig';
import './sw-condition-value-between-date.scss';

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default {
    template,

    emits: ['update:value'],

    props: {
        value: {
            type: Object,
            required: false,
            default: null,
        },

        dateType: {
            type: String,
            required: false,
            default: 'date',
            validator: (value) =>
                [
                    'date',
                    'datetime',
                ].includes(value),
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        from: {
            get() {
                return this.value?.from ?? null;
            },
            set(from) {
                this.$emit('update:value', {
                    from,
                    to: this.value?.to ?? null,
                });
            },
        },

        to: {
            get() {
                return this.value?.to ?? null;
            },
            set(to) {
                this.$emit('update:value', {
                    from: this.value?.from ?? null,
                    to,
                });
            },
        },
    },
};

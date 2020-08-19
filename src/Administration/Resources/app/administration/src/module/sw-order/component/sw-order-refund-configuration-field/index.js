import template from './sw-order-refund-configuration-field.html.twig';

const { Component } = Shopware;

Component.register('sw-order-refund-configuration-field', {
    template,

    props: {
        element: {
            type: Object,
            required: true
        },
        value: {
            type: [Object, String, Boolean, Number],
            required: false
        }
    },

    computed: {
        currentLocale() {
            return this.$root.$i18n.locale;
        },
        fieldLabel() {
            if (!this.element.label || !this.element.label[this.currentLocale]) {
                return 'missing label';
            }

            return this.element.label[this.currentLocale];
        },
        fieldId() {
            return `order-refund-configuration-field--${this.element.name.replace(/\./g, '-')}`;
        },
        error() {
            if (!this.element.required || !!this.value) {
                return null;
            }

            return {
                detail: this.$tc('sw-order.refundCard.createRefundModal.refundOptionRequired')
            };
        }
    },

    methods: {
        getOptionLabel(option) {
            if (!option.label || !option.label[this.currentLocale]) {
                return 'missing label';
            }

            return option.label[this.currentLocale];
        },

        onChange(value) {
            this.$emit('change', value);
        }
    }
});

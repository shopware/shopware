import template from './sw-product-variant-info.html.twig';
import './sw-product-variant-info.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Component which renders the variations of variant products.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-product-variant-info :variations="variations"></sw-product-variant-info>
 */
Component.register('sw-product-variant-info', {
    template,

    props: {
        variations: {
            type: Array,
            required: false
        },
        width: {
            type: Number,
            required: false,
            default: 500
        },
        tooltipPosition: {
            type: String,
            required: false,
            default: 'top',
            validValues: ['top', 'bottom', 'left', 'right'],
            validator(value) {
                return ['top', 'bottom', 'left', 'right'].includes(value);
            }
        },
        showDelay: {
            type: Number,
            required: false
        },
        hideDelay: {
            type: Number,
            required: false
        }
    },

    data() {
        return {
            helpText: ''
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setHelpText();
        },

        setHelpText() {
            this.helpText += `${this.$slots.default[0].text}`;

            if (this.variations.length > 0) {
                this.helpText += ' (';
                this.variations.forEach((variant) => {
                    this.helpText += `${variant.group} : ${variant.option}`;

                    if (variant !== this.variations[this.variations.length - 1]) {
                        this.helpText += ' | ';
                    }
                });
                this.helpText += ') ';
            }
        }
    }
});

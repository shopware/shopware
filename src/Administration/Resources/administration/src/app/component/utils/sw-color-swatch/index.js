import { Component } from 'src/core/shopware';
import template from './sw-color-swatch.html.twig';
import './sw-color-swatch.less';

Component.register('sw-color-swatch', {
    props: {
        variant: {
            type: String,
            required: false
        },
        color: {
            type: String,
            required: false
        },
        rounded: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        variantClass() {
            return {
                [`sw-color-swatch__${this.variant}`]: true,
                'sw-color-swatch__rounded': this.rounded
            };
        }
    },

    template
});

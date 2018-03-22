import { Component } from 'src/core/shopware';
import template from './sw-color-swatch.html.twig';
import './sw-color-swatch.less';

Component.register('sw-color-swatch', {
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default'
        },
        color: {
            type: String,
            required: false,
            default: ''
        },
        rounded: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        colorStyle() {
            if (!this.color.length) {
                return '';
            }
            return `background:${this.color}`;
        },
        variantClass() {
            return {
                [`sw-color-swatch__${this.variant}`]: true,
                'sw-color-swatch__rounded': this.rounded
            };
        }
    }
});

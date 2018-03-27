import { Component } from 'src/core/shopware';
import template from './sw-color-badge.html.twig';
import './sw-color-badge.less';

Component.register('sw-color-badge', {
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
                [`is--${this.variant}`]: true,
                'is--rounded': this.rounded
            };
        }
    }
});

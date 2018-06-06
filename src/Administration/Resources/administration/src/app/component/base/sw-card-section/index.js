import { Component } from 'src/core/shopware';
import template from './sw-card-section.html.twig';
import './sw-card-section.less';

Component.register('sw-card-section', {
    template,

    props: {
        divider: {
            type: String,
            required: false,
            default: '',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['top', 'right', 'bottom', 'left'].includes(value);
            }
        },
        secondary: {
            type: Boolean,
            required: false,
            default: false
        },
        slim: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        cardSectionClasses() {
            return {
                [`sw-card-section--divider-${this.divider}`]: this.divider,
                'sw-card-section--secondary': this.secondary,
                'sw-card-section--slim': this.slim
            };
        }
    }
});

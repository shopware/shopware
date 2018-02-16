import { Component } from 'src/core/shopware';
import './sw-button.less';
import template from './sw-button.html.twig';

Component.register('sw-button', {
    props: {
        isPrimary: {
            type: Boolean,
            required: false,
            default: false
        },
        isGhost: {
            type: Boolean,
            required: false,
            default: false
        },
        isSmall: {
            type: Boolean,
            required: false,
            default: false
        },
        isLarge: {
            type: Boolean,
            required: false,
            default: false
        },
        isDisabled: {
            type: Boolean,
            required: false,
            default: false
        },
        link: {
            type: String,
            required: false,
            default: ''
        }
    },
    computed: {
        buttonClasses() {
            return {
                'sw-button__element--primary': this.isPrimary,
                'sw-button__element--ghost': this.isGhost,
                'sw-button__element--disabled': this.isDisabled,
                'sw-button__element--small': this.isSmall,
                'sw-button__element--large': this.isLarge,
                'sw-button--block': this.isBlock
            };
        }
    },
    template
});

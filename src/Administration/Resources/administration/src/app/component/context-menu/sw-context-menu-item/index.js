import { Component } from 'src/core/shopware';
import template from './sw-context-menu-item.html.twig';
import './sw-context-menu-item.less';

Component.register('sw-context-menu-item', {
    template,

    props: {
        icon: {
            type: String,
            required: false
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        routerLink: {
            type: Object,
            required: false
        },
        variant: {
            type: String,
            required: false,
            default: '',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['success', 'danger', 'warning'].includes(value);
            }
        }
    },

    computed: {
        contextMenuItemStyles() {
            return {
                [`sw-context-menu-item--${this.variant}`]: this.variant,
                'is--disabled': this.disabled,
                'sw-context-menu-item--icon': this.icon
            };
        }
    }
});

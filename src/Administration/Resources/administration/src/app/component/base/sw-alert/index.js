import { Component } from 'src/core/shopware';
import template from './sw-alert.html.twig';
import './sw-alert.less';

Component.register('sw-alert', {
    template,

    props: {
        variant: {
            type: String,
            default: '',
            validator(value) {
                return ['info', 'warning', 'error', 'success'].includes(value);
            }
        },
        appearance: {
            type: String,
            default: 'default',
            validator(value) {
                return ['default', 'notification', 'system'].includes(value);
            }
        },
        title: {
            type: String,
            required: false
        },
        showIcon: {
            type: Boolean,
            required: false,
            default: true
        },
        closable: {
            type: Boolean,
            required: false,
            default: false
        },
        notificationIndex: {
            type: String,
            required: false
        }
    },
    computed: {
        alertClasses() {
            return [
                `sw-alert--${this.variant}`,
                `sw-alert--${this.appearance}`,
                {
                    'sw-alert--no-icon': !this.showIcon,
                    'sw-alert--closable': this.closable
                }
            ];
        },

        alertIcon() {
            const iconConfig = {
                info: 'default-badge-info',
                warning: 'default-badge-warning',
                error: 'default-badge-error',
                success: 'default-basic-checkmark-circle'
            };

            return iconConfig[this.variant] || 'default-bell-bell';
        }
    }
});

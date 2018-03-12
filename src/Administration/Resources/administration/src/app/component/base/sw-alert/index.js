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
                if (!value.length) {
                    return true;
                }
                return ['info', 'warning', 'error', 'success'].indexOf(value) !== -1;
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
        system: {
            type: Boolean,
            required: false,
            default: false
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
                {
                    'sw-alert--system': this.system,
                    'sw-alert--no-icon': !this.showIcon
                    'sw-alert--closable': this.closable,
                }
            ];
        },

        alertIcon() {
            let alertIcon;

            switch (this.variant) {
            case 'info':
                alertIcon = 'default-badge-info';
                break;
            case 'warning':
                alertIcon = 'default-badge-warning';
                break;
            case 'error':
                alertIcon = 'default-badge-error';
                break;
            case 'success':
                alertIcon = 'default-basic-checkmark-circle';
                break;
            default:
                alertIcon = 'default-bell-bell';
            }

            return alertIcon;
        }
    }
});

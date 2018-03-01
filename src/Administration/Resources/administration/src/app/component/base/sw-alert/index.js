import { Component } from 'src/core/shopware';
import './sw-alert.less';
import template from './sw-alert.html.twig';

Component.register('sw-alert', {
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
        }
    },
    computed: {
        alertClasses() {
            return `sw-alert--${this.variant}`;
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
    },

    template
});

import { Component } from 'src/core/shopware';
import './sw-alert.less';
import template from './sw-alert.html.twig';

Component.register('sw-alert', {
    props: {
        info: {
            type: Boolean,
            required: false,
            default: false
        },
        warning: {
            type: Boolean,
            required: false,
            default: false
        },
        error: {
            type: Boolean,
            required: false,
            default: false
        },
        success: {
            type: Boolean,
            required: false,
            default: false
        },
        title: {
            type: String,
            required: false
        }
    },
    computed: {
        alertClasses() {
            return {
                'sw-alert--info': this.info,
                'sw-alert--warning': this.warning,
                'sw-alert--error': this.error,
                'sw-alert--success': this.success
            };
        },

        alertIcon() {
            if (this.info) {
                return 'default-badge-info';
            }

            if (this.warning) {
                return 'default-badge-warning';
            }

            if (this.error) {
                return 'default-badge-error';
            }

            if (this.success) {
                return 'default-basic-checkmark-circle';
            }

            return 'default-bell-bell';
        }
    },

    template
});

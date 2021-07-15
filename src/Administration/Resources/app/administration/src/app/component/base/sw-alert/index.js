import template from './sw-alert.html.twig';
import './sw-alert.scss';

const { Component } = Shopware;

/**
 * @description
 * The <u>sw-alert</u> component is used to convey important information to the user. It comes in 4 variations,
 * <strong>success</strong>, <strong>info</strong>, <strong>warning</strong> and <strong>error</strong>. These have
 * default icons assigned which can be changed and represent different actions
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-alert variant="info" title="Example title" :closable="true">
 *    Sample text
 * </sw-alert>
 */
Component.register('sw-alert', {
    template,

    props: {
        variant: {
            type: String,
            default: 'info',
            validValues: ['info', 'warning', 'error', 'success'],
            validator(value) {
                return ['info', 'warning', 'error', 'success'].includes(value);
            },
        },
        appearance: {
            type: String,
            default: 'default',
            validValues: ['default', 'notification', 'system'],
            validator(value) {
                return ['default', 'notification', 'system'].includes(value);
            },
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        showIcon: {
            type: Boolean,
            required: false,
            default: true,
        },
        closable: {
            type: Boolean,
            required: false,
            default: false,
        },
        notificationIndex: {
            type: String,
            required: false,
            default: null,
        },
    },
    computed: {
        alertIcon() {
            const iconConfig = {
                info: 'default-badge-info',
                warning: 'default-badge-warning',
                error: 'default-badge-error',
                success: 'default-basic-checkmark-circle',
            };

            return iconConfig[this.variant] || 'default-bell-bell';
        },

        hasActionSlot() {
            return !!this.$slots.actions;
        },

        alertClasses() {
            return [
                `sw-alert--${this.variant}`,
                `sw-alert--${this.appearance}`,
                {
                    'sw-alert--icon': this.showIcon,
                    'sw-alert--no-icon': !this.showIcon,
                    'sw-alert--closable': this.closable,
                    'sw-alert--actions': this.hasActionSlot,
                },
            ];
        },

        alertBodyClasses() {
            return {
                'sw-alert__body--icon': this.showIcon,
                'sw-alert__body--closable': this.closable,
            };
        },
    },
});

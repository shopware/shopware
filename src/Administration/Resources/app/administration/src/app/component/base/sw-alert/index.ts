import type { NotificationType } from 'src/app/mixin/notification.mixin';
import type { PropType } from 'vue';
import template from './sw-alert.html.twig';
import './sw-alert.scss';

const { Component } = Shopware;
type AppearanceType = 'default' | 'notification' | 'system';
type CssClassesObject = { [key: string]: boolean };
type CssClasses = Array<string | CssClassesObject> | CssClassesObject;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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
            type: String as PropType<NotificationType>,
            required: false,
            default: 'info',
            validValues: ['info', 'warning', 'error', 'success', 'neutral'],
            validator(value: string): boolean {
                return ['info', 'warning', 'error', 'success', 'neutral'].includes(value);
            },
        },
        appearance: {
            type: String as PropType<AppearanceType>,
            required: false,
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
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
        icon: {
            type: String,
            required: false,
            default: null,
        },
    },
    computed: {
        alertIcon(): string {
            if (this.icon) {
                return this.icon;
            }

            const iconConfig: { [type: string]: string } = {
                info: 'regular-info-circle',
                warning: 'regular-exclamation-triangle',
                error: 'regular-exclamation-circle',
                success: 'regular-check-circle',
                neutral: 'regular-info-circle',
            };

            return iconConfig[this.variant] || 'regular-bell';
        },

        hasActionSlot(): boolean {
            return !!this.$slots.actions;
        },

        alertClasses(): CssClasses {
            const variantClass = `sw-alert--${this.variant}`;

            return [
                `sw-alert--${this.appearance}`,
                {
                    [variantClass]: this.appearance !== 'system',
                    'sw-alert--icon': this.showIcon,
                    'sw-alert--no-icon': !this.showIcon,
                    'sw-alert--closable': this.closable,
                    'sw-alert--actions': this.hasActionSlot,
                },
            ];
        },

        alertBodyClasses(): CssClasses {
            return {
                'sw-alert__body--icon': this.showIcon,
                'sw-alert__body--closable': this.closable,
            };
        },
    },
});

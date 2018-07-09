import { Component } from 'src/core/shopware';
import template from './sw-user-card.html.twig';
import './sw-user-card.less';

Component.register('sw-user-card', {
    template,

    props: {
        user: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        title: {
            type: String,
            required: true,
            default: ''
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions;
        },

        moduleColor() {
            return this.$route.meta.$module.color;
        },

        userName() {
            const user = this.user;

            if (!user.salutation && !user.firstName && !user.lastName) {
                return '';
            }

            const salutation = user.salutation ? user.salutation : '';
            const firstName = user.firstName ? user.firstName : '';
            const lastName = user.lastName ? user.lastName : '';

            return `${salutation} ${firstName} ${lastName}`;
        }
    }
});

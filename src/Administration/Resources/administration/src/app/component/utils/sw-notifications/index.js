import { Component } from 'src/core/shopware';
import template from './sw-notifications.html.twig';
import './sw-notifications.less';

Component.register('sw-notifications', {
    template,

    props: {
        position: {
            type: String,
            required: false,
            default: 'topRight',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['topRight', 'bottomLeft', 'bottomRight'].indexOf(value) !== -1;
            }
        },
        notificationsGap: {
            type: String,
            default: '20px'
        }
    },

    methods: {
        onRemove(event) {
            this.$store.commit('notification/removeNotification', event);
        }
    },

    computed: {
        notifications() {
            return this.$store.state.notification.notifications;
        },

        notificationsStyle() {
            let notificationsStyle;
            const notificationsGap = this.notificationsGap;

            switch (this.position) {
            case 'bottomLeft':
                notificationsStyle = {
                    top: 'auto',
                    right: 'auto',
                    bottom: notificationsGap,
                    left: notificationsGap
                };
                break;
            case 'bottomRight':
                notificationsStyle = {
                    top: 'auto',
                    right: notificationsGap,
                    bottom: notificationsGap,
                    left: 'auto'
                };
                break;
            default:
                notificationsStyle = {
                    top: notificationsGap,
                    right: notificationsGap,
                    bottom: 'auto',
                    left: 'auto'
                };
            }

            return notificationsStyle;
        }
    }
});

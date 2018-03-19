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
                return ['topRight', 'bottomRight'].includes(value);
            }
        },
        notificationsGap: {
            type: String,
            default: '20px'
        },
        limit: {
            type: Number,
            default: 5
        }
    },

    methods: {
        onClose(event) {
            this.$store.commit('notification/removeNotification', event);
        }
    },

    computed: {
        notifications: {
            get() {
                const notifications = this.$store.state.notification.notifications;

                if (notifications.length > this.limit) {
                    this.$store.commit('notification/removeNotification');
                }

                return notifications;
            }
        },

        notificationsStyle() {
            let notificationsGap = this.notificationsGap;

            if (`${parseInt(notificationsGap, 10)}` === notificationsGap) {
                notificationsGap = `${notificationsGap}px`;
            }

            if (this.position === 'bottomRight') {
                return {
                    top: 'auto',
                    right: notificationsGap,
                    bottom: notificationsGap,
                    left: 'auto'
                };
            }

            return {
                top: notificationsGap,
                right: notificationsGap,
                bottom: 'auto',
                left: 'auto'
            };
        }
    }
});

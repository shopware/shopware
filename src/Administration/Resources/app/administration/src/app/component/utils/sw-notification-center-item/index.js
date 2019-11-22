import './sw-notification-center-item.scss';
import template from './sw-notification-center-item.html.twig';

const { Component } = Shopware;
const { date } = Shopware.Utils.format;

Component.register('sw-notification-center-item', {
    template,

    props: {
        notification: {
            type: Object,
            required: true
        }
    },

    computed: {
        itemHeaderClass() {
            return {
                'sw-notification-center-item__header--is-new': !this.notification.visited
            };
        },
        dateFormatted() {
            if (this.isNotificationFromSameDay()) {
                return date(
                    this.notification.timestamp,
                    {
                        day: undefined,
                        month: undefined,
                        year: undefined,
                        hour: '2-digit',
                        minute: '2-digit'
                    }
                );
            }

            return date(
                this.notification.timestamp,
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            );
        },
        notificationActions() {
            return this.notification.actions.filter((action) => {
                return action.route;
            });
        }
    },

    methods: {
        isNotificationFromSameDay() {
            const timestamp = this.notification.timestamp;
            const now = new Date();
            return timestamp.getDate() === now.getDate() &&
                timestamp.getMonth() === now.getMonth() &&
                timestamp.getFullYear() === now.getFullYear();
        },

        onDelete() {
            Shopware.State.commit('notification/removeNotification', this.notification);
        },

        handleAction(action) {
            // Allow external links for example to the shopware account or store
            if (Shopware.Utils.string.isUrl(action.route)) {
                window.open(action.route);
                return;
            }

            this.$router.push(action.route);
            this.$emit('center-close');
        }
    }
});

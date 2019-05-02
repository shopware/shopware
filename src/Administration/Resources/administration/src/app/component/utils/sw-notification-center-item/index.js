import './sw-notification-center-item.scss';
import { date } from 'src/core/service/utils/format.utils';
import template from './sw-notification-center-item.html.twig';

export default {
    name: 'sw-notification-center-item',
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
            this.$store.commit('notification/removeNotification', this.notification);
        }
    }
};

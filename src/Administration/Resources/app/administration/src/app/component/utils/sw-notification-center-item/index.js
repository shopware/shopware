/**
 * @sw-package framework
 */

import './sw-notification-center-item.scss';
import template from './sw-notification-center-item.html.twig';

/**
 * @private
 */
export default {
    template,

    emits: ['center-close'],

    props: {
        notification: {
            type: Object,
            required: true,
        },
    },

    computed: {
        itemHeaderClass() {
            return {
                'sw-notification-center-item__header--is-new': !this.notification.visited,
            };
        },

        notificationActions() {
            return this.notification.actions.filter((action) => {
                return action.route;
            });
        },
    },

    methods: {
        getTranslatedTitle(notification) {
            if (!notification.title) {
                return '';
            }

            return this.$te(notification.title) ? this.$t(notification.title) : notification.title;
        },

        getTranslatedMessage(notification) {
            const message = this.$te(notification.message) ? this.$t(notification.message) : notification.message;

            return this.$sanitize(message, {
                ALLOWED_TAGS: [
                    'a',
                    'b',
                    'i',
                    'u',
                    'strong',
                    'em',
                    'br',
                ],
                ALLOWED_ATTR: [
                    'href',
                    'target',
                ],
            });
        },

        isNotificationFromSameDay() {
            const timestamp = this.notification.timestamp;
            const now = new Date();
            return (
                timestamp.getDate() === now.getDate() &&
                timestamp.getMonth() === now.getMonth() &&
                timestamp.getFullYear() === now.getFullYear()
            );
        },

        onDelete() {
            Shopware.Store.get('notification').removeNotification(this.notification);
        },

        handleAction(action) {
            // Allow external links for example to the shopware account or store
            if (Shopware.Utils.string.isUrl(action.route)) {
                window.open(action.route);
                return;
            }

            this.$router.push(action.route);
            this.$emit('center-close');
        },
    },
};

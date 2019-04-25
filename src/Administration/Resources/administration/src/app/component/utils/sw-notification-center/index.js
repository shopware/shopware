import template from './sw-notification-center.html.twig';
import './sw-notification-center.scss';

export default {
    name: 'sw-notification-center',
    template,

    data() {
        return {
            additionalContextMenuClasses: {
                'sw-notification-center__context-container': true
            }
        };
    },

    computed: {
        notifications() {
            return this.$store.state.notification.notifications;
        },

        additionalContextButtonClass() {
            return {
                'sw-notification-center__context-button--new-available': this.notifications.some(n => !n.visited)
            };
        }
    },

    methods: {
        onContextMenuClose() {
            this.$store.dispatch('notification/setAllNotificationsVisited');
        }
    }
};

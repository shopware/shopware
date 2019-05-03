import template from './sw-notification-center.html.twig';
import './sw-notification-center.scss';

export default {
    name: 'sw-notification-center',
    template,

    data() {
        return {
            additionalContextMenuClasses: {
                'sw-notification-center__context-container': true
            },
            showDeleteModal: false
        };
    },

    computed: {
        notifications() {
            return this.$store.getters['notification/getNotifications'];
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
        },
        openDeleteModal() {
            this.showDeleteModal = true;
        },
        onConfirmDelete() {
            this.$store.dispatch('notification/setNotifications', {});
            this.showDeleteModal = false;
        },
        onCloseDeleteModal() {
            this.showDeleteModal = false;
        }
    }
};

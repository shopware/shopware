import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
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
        onContextMenuOpen() {
            this.$store.commit('notification/setWorkerProcessPollInterval', POLL_FOREGROUND_INTERVAL);
        },
        onContextMenuClose() {
            this.$store.dispatch('notification/setAllNotificationsVisited');
            this.$store.commit('notification/setWorkerProcessPollInterval', POLL_BACKGROUND_INTERVAL);
        },
        openDeleteModal() {
            this.showDeleteModal = true;
        },
        onConfirmDelete() {
            this.$store.commit('notification/clearNotificationsForCurrentUser');
            this.showDeleteModal = false;
        },
        onCloseDeleteModal() {
            this.showDeleteModal = false;
        }
    }
};

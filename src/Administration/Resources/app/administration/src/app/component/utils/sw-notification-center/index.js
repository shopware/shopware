import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-notification-center.html.twig';
import './sw-notification-center.scss';

const { Mixin } = Shopware;

/**
 * @sw-package framework
 * @private
 */
export default {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isOpened: false,
            optionsMenuOpen: false,
            isBellRinging: false,
            showDeleteModal: false,
            unsubscribeFromStore: null,
        };
    },

    computed: {
        notifications() {
            return Object.values(Shopware.Store.get('notification').notifications).reverse();
        },

        hasNotifications() {
            return this.notifications.length > 0;
        },

        additionalContextButtonClass() {
            return {
                'sw-notification-center__context-button--new-available': this.notifications.some((n) => !n.visited),
            };
        },
    },

    watch: {
        isOpened(value) {
            this.onVisibilityChange(value);
        },
    },

    created() {
        this.unsubscribeFromStore = Shopware.Store.get('notification').$onAction(this.createNotificationFromSystemError);
        Shopware.Utils.EventBus.on('on-change-notification-center-visibility', this.changeVisibility);
    },

    beforeUnmount() {
        this.unsubscribeFromStore?.();

        Shopware.Utils.EventBus.off('on-change-notification-center-visibility', this.changeVisibility);
    },

    methods: {
        onVisibilityChange(isOpened) {
            const store = Shopware.Store.get('notification');

            if (isOpened) {
                store.workerProcessPollInterval = POLL_FOREGROUND_INTERVAL;
                return;
            }

            store.setAllNotificationsVisited();
            store.workerProcessPollInterval = POLL_BACKGROUND_INTERVAL;
        },

        openDeleteModal() {
            this.optionsMenuOpen = false;
            this.isOpened = false;
            this.showDeleteModal = true;
        },

        onConfirmDelete() {
            Shopware.Store.get('notification').clearNotificationsForCurrentUser();
            this.showDeleteModal = false;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onEmptyStateBellClick() {
            this.isBellRinging = true;
        },

        togglePanel() {
            this.changeVisibility(!this.isOpened);
        },

        onPanelClose() {
            if (this.optionsMenuOpen) {
                return;
            }

            this.changeVisibility(false);
        },

        changeVisibility(visible) {
            this.isOpened = visible;

            if (!visible) {
                this.showDeleteModal = false;
                this.optionsMenuOpen = false;
            }
        },

        createNotificationFromSystemError({ name, args }) {
            if (name !== 'addSystemError') {
                return;
            }

            this.createSystemNotificationError({
                id: args.id,
                message: args.error.detail,
            });
        },
    },
};

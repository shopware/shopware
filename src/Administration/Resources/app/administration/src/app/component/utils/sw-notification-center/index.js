/**
 * @sw-package framework
 */

import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-notification-center.html.twig';
import './sw-notification-center.scss';

const { Mixin } = Shopware;

/**
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
            additionalContextMenuClasses: {
                'sw-notification-center__context-container': true,
            },
            isBellRinging: false,
            showDeleteModal: false,
            unsubscribeFromStore: null,
        };
    },

    computed: {
        notifications() {
            return Object.values(Shopware.Store.get('notification').notifications).reverse();
        },

        additionalContextButtonClass() {
            return {
                'sw-notification-center__context-button--new-available': this.notifications.some((n) => !n.visited),
            };
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
        onOpenChange(isOpen) {
            this.isBellRinging = false;

            if (isOpen) {
                this.onContextMenuOpen();
                return;
            }

            this.onContextMenuClose();
        },

        onContextMenuOpen() {
            Shopware.Store.get('notification').workerProcessPollInterval = POLL_FOREGROUND_INTERVAL;
        },

        onContextMenuClose() {
            Shopware.Store.get('notification').setAllNotificationsVisited();
            Shopware.Store.get('notification').workerProcessPollInterval = POLL_BACKGROUND_INTERVAL;
        },

        openDeleteModal() {
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

        changeVisibility(visible) {
            const contextButton = this.$refs.notificationCenterContextButton;

            if (!contextButton) {
                return;
            }

            if (visible) {
                contextButton.openMenu();
                return;
            }

            contextButton.closeMenu();
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

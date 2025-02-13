/**
 * @sw-package framework
 */

import { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
import template from './sw-notification-center.html.twig';
import './sw-notification-center.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
Component.register('sw-notification-center', {
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

    beforeDestroyed() {
        this.unsubscribeFromStore?.();

        this.$root.$off('on-change-notification-center-visibility', this.changeVisibility);
    },

    methods: {
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
        changeVisibility(visible) {
            if (this.$refs.notificationCenterContextButton === undefined) {
                return;
            }

            if (visible) {
                this.$refs.notificationCenterContextButton.openMenu();
                return;
            }

            this.$refs.notificationCenterContextButton.showMenu = false;
            this.$refs.notificationCenterContextButton.removeMenuFromBody();
            this.$refs.notificationCenterContextButton.$emit('context-menu-after-close');
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
});

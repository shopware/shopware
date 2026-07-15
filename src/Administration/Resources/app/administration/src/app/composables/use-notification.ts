/**
 * @sw-package framework
 */
import type { NotificationType, NotificationVariant } from '../store/notification.store';

/**
 * Composable alternative to the `notification` mixin.
 *
 * The logic is duplicated from `src/app/mixin/notification.mixin.ts` rather than
 * delegated: the mixin's `createNotification*` helpers call `this.createNotification`,
 * so a component that overrides `createNotification` changes their behaviour.
 * Delegating would drop that override hook, so the mixin is kept untouched for
 * legacy Options API components and this composable is the standalone equivalent
 * for `<script setup>` components (and the SFC migration codemod).
 *
 * @private
 */
export function useNotification(): {
    createNotification: (notification: NotificationType) => string | null;
    createNotificationSuccess: (config: NotificationType) => void;
    createNotificationInfo: (config: NotificationType) => void;
    createNotificationWarning: (config: NotificationType) => void;
    createNotificationError: (config: NotificationType) => void;
    createSystemNotificationSuccess: (config: NotificationType) => void;
    createSystemNotificationInfo: (config: NotificationType) => void;
    createSystemNotificationWarning: (config: NotificationType) => void;
    createSystemNotificationError: (config: NotificationType) => void;
    createSystemNotification: (config: NotificationType) => void;
} {
    function createNotification(notification: NotificationType): string | null {
        return Shopware.Store.get('notification').createNotification(notification);
    }

    function createNotificationSuccess(config: NotificationType): void {
        void createNotification({
            variant: 'success' as NotificationVariant,
            title: 'global.default.success',
            ...config,
        });
    }

    function createNotificationInfo(config: NotificationType): void {
        void createNotification({
            variant: 'info' as NotificationVariant,
            title: 'global.default.info',
            ...config,
        });
    }

    function createNotificationWarning(config: NotificationType): void {
        void createNotification({
            variant: 'warning' as NotificationVariant,
            title: 'global.default.warning',
            ...config,
        });
    }

    function createNotificationError(config: NotificationType): void {
        void createNotification({
            variant: 'error' as NotificationVariant,
            title: 'global.default.error',
            ...config,
        });
    }

    function createSystemNotificationSuccess(config: NotificationType): void {
        void createNotification({
            variant: 'success' as NotificationVariant,
            system: true,
            ...config,
        });
    }

    function createSystemNotificationInfo(config: NotificationType): void {
        void createNotification({
            variant: 'info' as NotificationVariant,
            system: true,
            ...config,
        });
    }

    function createSystemNotificationWarning(config: NotificationType): void {
        void createNotification({
            variant: 'warning' as NotificationVariant,
            system: true,
            ...config,
        });
    }

    function createSystemNotificationError(config: NotificationType): void {
        void createNotification({
            variant: 'error' as NotificationVariant,
            system: true,
            ...config,
        });
    }

    function createSystemNotification(config: NotificationType): void {
        void createNotification({ system: true, ...config });
    }

    return {
        createNotification,
        createNotificationSuccess,
        createNotificationInfo,
        createNotificationWarning,
        createNotificationError,
        createSystemNotificationSuccess,
        createSystemNotificationInfo,
        createSystemNotificationWarning,
        createSystemNotificationError,
        createSystemNotification,
    };
}

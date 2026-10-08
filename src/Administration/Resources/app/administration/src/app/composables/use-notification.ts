/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import type { NotificationType, NotificationVariant } from '../store/notification.store';

/**
 * Composable alternative to the `notification` mixin.
 *
 * The logic is duplicated from `src/app/mixin/notification.mixin.ts` rather than delegated to it,
 * because the mixin's `createNotification*` helpers call `this.createNotification`: delegating would
 * keep resolving that call against a component's override, which a composable has no `this` to read.
 * The mixin stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/notification.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useNotification(): {
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

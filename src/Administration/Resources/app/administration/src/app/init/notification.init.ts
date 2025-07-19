import type { NotificationVariant } from 'src/app/store/notification.store';
/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeNotifications(): void {
    // Handle incoming notifications from the ExtensionAPI
    Shopware.ExtensionAPI.handle('notificationDispatch', (notificationOptions) => {
        // @ts-expect-error - t is callable
        const message = notificationOptions.message ?? Shopware.Snippet.tc('global.notification.noMessage');
        // @ts-expect-error - tc is callable
        const title = notificationOptions.title ?? Shopware.Snippet.tc('global.notification.noTitle');
        const actions = notificationOptions.actions ?? [];
        const appearance = notificationOptions.appearance ?? 'notification';
        const growl = notificationOptions.growl ?? true;

        let variant: NotificationVariant = 'info';

        if (notificationOptions.variant) {
            // @ts-expect-error - new variant types are not yet in the SDK
            if (notificationOptions.variant === 'success' || notificationOptions.variant === 'positive') {
                variant = 'positive';
            }

            // @ts-expect-error - new variant types are not yet in the SDK
            if (notificationOptions.variant === 'error' || notificationOptions.variant === 'critical') {
                variant = 'critical';
            }

            // @ts-expect-error - new variant types are not yet in the SDK
            if (notificationOptions.variant === 'warning' || notificationOptions.variant === 'attention') {
                variant = 'attention';
            }

            // @ts-expect-error - new variant types are not yet in the SDK
            if (notificationOptions.variant === 'info' || notificationOptions.variant === 'neutral') {
                variant = 'info';
            }
        }

        Shopware.Store.get('notification').createNotification({
            variant: variant,
            title: title,
            message: message,
            growl: growl,
            actions: actions,
            system: appearance === 'system',
        });
    });
}

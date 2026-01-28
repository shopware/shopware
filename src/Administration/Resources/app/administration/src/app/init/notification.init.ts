import type { NotificationVariant } from 'src/app/store/notification.store';
import useSession from 'src/app/composables/use-session';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeNotifications(): void {
    // Handle incoming notifications from the ExtensionAPI
    Shopware.ExtensionAPI.handle('notificationDispatch', (notificationOptions) => {
        // Store translation keys directly in title/message fields
        // If no title/message provided, use the translation key (template will translate)
        const message = notificationOptions.message ?? 'global.notification.noMessage';
        const title = notificationOptions.title ?? 'global.notification.noTitle';
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

    // Watch for locale changes and re-translate all notifications
    Shopware.Vue.watch(useSession().currentLocale, () => {
        const notificationStore = Shopware.Store.get('notification');
        if (notificationStore.retranslateAllNotifications) {
            notificationStore.retranslateAllNotifications();
        }
    });
}

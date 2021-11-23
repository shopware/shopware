import { handle } from '@shopware-ag/admin-extension-sdk/es/channel';

export default function initializeNotifications(): void {
    // Handle incoming notifications from the ExtensionAPI
    handle('dispatchNotification', async (notificationOptions) => {
        const message = notificationOptions.message ?? 'Message is not defined';
        const title = notificationOptions.title ?? 'Title is not defined';
        const actions = notificationOptions.actions ?? [];
        const appearance = notificationOptions.appearance ?? 'notification';
        const growl = notificationOptions.growl ?? true;
        const variant = notificationOptions.variant ?? 'info';

        await Shopware.State.dispatch('notification/createNotification', {
            variant: variant,
            title: title,
            message: message,
            growl: growl,
            actions: actions,
            system: appearance === 'system',
        });
    });
}

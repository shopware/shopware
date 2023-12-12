import type { I18n } from 'vue-i18n';

/**
 * @package admin
 *
 * @private
 */
export default function initializeNotifications(): void {
    // Handle incoming notifications from the ExtensionAPI
    Shopware.ExtensionAPI.handle('notificationDispatch', async (notificationOptions) => {
        const viewRoot = Shopware.Application.view?.root;
        // @ts-expect-error
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/ban-types
        const $tc = viewRoot.$tc.bind(viewRoot) as I18n<{}, {}, {}, string, true>['global']['tc'];

        const message = notificationOptions.message ?? $tc('global.notification.noMessage');
        const title = notificationOptions.title ?? $tc('global.notification.noTitle');
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

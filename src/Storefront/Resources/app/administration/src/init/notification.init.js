/**
 * @sw-package discovery
 */

/**
 * Backend notifications (created via the PHP NotificationService) can only carry a `status`
 * and a `message`; the notification entity has no title column. The async theme compilation
 * notifications therefore arrive with a translatable message key but no title.
 *
 * We use the notification store's transformer hook to attach the matching title snippet key
 * whenever one of those messages is dispatched. Keeping it a snippet key (instead of a resolved
 * string) lets the notification templates translate it at render time and re-translate on a
 * language switch.
 */
const asyncCompilationTitles = {
    'sw-theme-manager.detail.asyncCompilation.started': 'sw-theme-manager.detail.asyncCompilation.startedTitle',
    'sw-theme-manager.detail.asyncCompilation.completed': 'sw-theme-manager.detail.asyncCompilation.completedTitle',
};

Object.entries(asyncCompilationTitles).forEach(([message, title]) => {
    Shopware.Store.get('notification').registerTransformer(message, (notification) => ({
        ...notification,
        title,
    }));
});
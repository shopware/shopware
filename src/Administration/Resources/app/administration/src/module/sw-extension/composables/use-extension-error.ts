/**
 * @sw-package checkout
 */
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import type { NotificationType } from 'src/app/store/notification.store';

interface ExtensionErrorService {
    handleErrorResponse: (errorResponse: unknown, translator: { $t: unknown }) => NotificationType[];
}

/** @private */
export interface UseExtensionErrorReturn {
    showExtensionErrors: (errorResponse: unknown) => void;
}

/**
 * Composable alternative to the `sw-extension-error` mixin: turns an extension
 * API error response into notifications. The mixin handed its own component
 * instance to `extensionErrorService`, which uses it only as a translator, so the
 * composable hands over `useI18n()`'s `t` under the same key instead.
 *
 * Keep this and `src/module/sw-extension/mixin/sw-extension-error.mixin.js` in sync —
 * change both together.
 *
 * @private
 */
export function useExtensionError(): UseExtensionErrorReturn {
    const { t } = useI18n();
    const { createNotificationError } = useNotification();

    function showExtensionErrors(errorResponse: unknown): void {
        // `extensionErrorService` is registered in the DI container but missing from its type map.
        const errorService = Shopware.Service(
            'extensionErrorService' as keyof ServiceContainer,
        ) as unknown as ExtensionErrorService;

        errorService.handleErrorResponse(errorResponse, { $t: t }).forEach((notification) => {
            createNotificationError(notification);
        });
    }

    return { showExtensionErrors };
}

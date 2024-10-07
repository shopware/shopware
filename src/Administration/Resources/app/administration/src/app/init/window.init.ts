/**
 * @package admin
 */

/* Is covered by E2E tests */
/* istanbul ignore file */
import type { Router } from 'vue-router';

/**
 * @private
 */
export default function initializeWindow(): void {
    // Handle incoming window requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('windowReload', () => {
        window.location.reload();
    });

    Shopware.ExtensionAPI.handle('windowRedirect', ({ newTab, url }) => {
        if (newTab) {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    });

    Shopware.ExtensionAPI.handle('windowRouterPush', ({ name, params, path, replace }) => {
        const $router = Shopware.Application.view?.router as unknown as Router;

        if (!$router) {
            return;
        }

        void $router.push({
            name: name && name.length > 0 ? name : undefined,
            params,
            path: path && path.length > 0 ? path : '',
            replace: replace ?? false,
        });
    });
}

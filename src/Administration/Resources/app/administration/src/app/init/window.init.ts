/**
 * @package admin
 */

/* Is covered by E2E tests */
/* istanbul ignore file */
import type VueRouter from 'vue-router';

/**
 * @deprecated tag:v6.6.0 - Will be private
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

    Shopware.ExtensionAPI.handle('windowRouterPush', ({
        name,
        params,
        path,
        replace,
    }) => {
        const $router = Shopware.Application.view?.root?.$router as unknown as VueRouter;

        if (!$router) {
            return;
        }

        void $router.push({
            name: name && name.length > 0 ? name : undefined,
            params,
            path: path && path.length > 0 ? path : undefined,
            replace: replace ?? false,
        });
    });
}

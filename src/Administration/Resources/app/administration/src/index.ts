/**
 * @sw-package framework
 */
import { ShopwareInstance } from 'src/core/shopware';
import { completeSsoReauthWindow } from 'src/core/helper/sso-reauth-window.helper';

// IIFE
void (async () => {
    // the SSO re-authentication window only reports back to its opener
    if (completeSsoReauthWindow()) {
        return;
    }

    // Set the global Shopware instance
    window.Shopware = ShopwareInstance;

    if (window._swLoginOverrides) {
        window._swLoginOverrides.forEach((script) => {
            script();
        });
    }

    // Import the main file
    await import('src/app/main');

    // Start the main application
    window.startApplication();
})();

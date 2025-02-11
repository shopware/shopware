/**
 * @sw-package framework
 * @experimental stableVersion:v6.7.0 feature:VITE
 */

// Needed because we build the files for traditional backend: https://vitejs.dev/guide/backend-integration.html
// eslint-disable-next-line import/no-unresolved
import 'vite/modulepreload-polyfill';
import './app/assets/scss/all.scss';

// Import the Shopware instance
void import('src/core/shopware').then(async ({ ShopwareInstance }) => {
    // Set the global Shopware instance
    window.Shopware = ShopwareInstance;

    // Import the main file
    await import('src/app/main.vite');

    // Start the main application and fingers crossed
    // that everything works as expected
    window.startApplication();
});

/**
 * @experimental stableVersion:v6.7.0 feature:VITE
 */

// Needed because we build the files for traditional backend: https://vitejs.dev/guide/backend-integration.html
// eslint-disable-next-line import/no-unresolved
import 'vite/modulepreload-polyfill';
import { configureCompat } from 'vue';
import './app/assets/scss/all.scss';

// Import the Shopware instance
void import('src/core/shopware').then(async ({ ShopwareInstance }) => {
    // Set the global Shopware instance
    window.Shopware = ShopwareInstance;

    // Take all keys out of Shopware.compatConfig but set them to true
    const compatConfig = Object.fromEntries(Object.keys(ShopwareInstance.compatConfig).map(key => [key, true]));

    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
    configureCompat(compatConfig);

    // Import the main file
    await import('src/app/main.vite');

    // Start the main application and fingers crossed
    // that everything works as expected
    window.startApplication();
});

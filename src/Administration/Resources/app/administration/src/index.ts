/**
 * @sw-package framework
 */
import './app/assets/scss/all.scss';

// Import the Shopware instance
void import('src/core/shopware').then(async ({ ShopwareInstance }) => {
    // Set the global Shopware instance
    window.Shopware = ShopwareInstance;

    // Import the main file
    await import('src/app/main');

    // Start the main application and fingers crossed
    // that everything works as expected
    window.startApplication();
});

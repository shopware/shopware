/**
 * @sw-package framework
 */
import './app/assets/scss/all.scss';
import 'inter-ui/inter.css';
import { ShopwareInstance } from 'src/core/shopware';

// IIFE
void (async () => {
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

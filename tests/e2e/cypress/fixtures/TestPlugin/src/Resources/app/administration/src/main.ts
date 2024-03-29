import "regenerator-runtime/runtime";
import { location } from '@shopware-ag/meteor-admin-sdk';

// Only execute extensionSDK commands when
// it is inside a iFrame (only needed for plugins)
// @ts-ignore
if (location.isIframe() && !window.parent.__Cypress__) {
    if (location.is(location.MAIN_HIDDEN)) {
        // Execute the base commands
        import('./base/mainCommands');
    } else {
        // Render different views
        import('./viewRenderer');
    }
}

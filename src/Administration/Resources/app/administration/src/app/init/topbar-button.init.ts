/**
 * @package customer-order
 *
 * @private
 */

import topBarButtonState from 'src/app/state/topbar-button.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTopBarButtons(): void {
    // @ts-expect-error - There are no types for this as it is private API
    Shopware.Store.register(topBarButtonState);

    // @ts-expect-error - There are no types for this as it is private API
    Shopware.ExtensionAPI.handle('__upsellingMenuButton', (configuration) => {
        const store = Shopware.Store.get('topBarButtonState');
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        store.buttons.push(configuration);
    });
}

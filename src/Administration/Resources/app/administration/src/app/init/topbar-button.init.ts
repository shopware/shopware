/**
 * @package customer-order
 *
 * @private
 * @description Apply for upselling service only, no public usage
 */

import topBarButtonState from 'src/app/store/topbar-button.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTopBarButtons(): void {
    Shopware.Store.register(topBarButtonState);

    // @ts-expect-error - There are no types for this as it is private API
    Shopware.ExtensionAPI.handle('__upsellingMenuButton', (configuration) => {
        const store = Shopware.Store.get('topBarButtonState');
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        store.buttons.push(configuration);
    });
}

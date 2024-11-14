/**
 * @package customer-order
 *
 * @private
 * @description Apply for upselling service only, no public usage
 */

import 'src/app/store/topbar-button.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTopBarButtons(): void {
    // @ts-expect-error - There are no types for this as it is private API
    Shopware.ExtensionAPI.handle('__upsellingMenuButton', (configuration) => {
        const store = Shopware.Store.get('topBarButton');
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        store.buttons.push(configuration);
    });
}

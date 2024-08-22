/**
 * @package customer-order
 *
 * @private
 */

import blockOverrideStore from 'src/app/store/block-override.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeBlockOverride(): void {
    Shopware.Store.register(blockOverrideStore);
}

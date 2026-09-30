/**
 * @sw-package framework
 * @private
 *
 * Imported for its side effect by `src/app/main.ts` and by the host preamble of `shopware:composables`.
 * An initializer would be too late: host `shopware:*` modules evaluate while `main.ts` is still being
 * imported, and they read `Shopware.Composables` at that moment.
 */
import { ShopwareInstance } from 'src/core/shopware';
import composables from './index';

ShopwareInstance.Composables = composables;

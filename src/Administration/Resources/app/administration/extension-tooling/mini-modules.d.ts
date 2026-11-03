/**
 * @sw-package framework
 *
 * Imports the `global.d.ts` of every mini-module, which adds its components to `PublicComponents`.
 * `admin-types.d.ts` imports this file, and `type-surface.spec.ts` fails when a `global.d.ts` is missing here.
 */

import '../src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix/global';
import '../src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrices/global';
import '../src/module/sw-media/component/sw-media-collapse/global';
import '../src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility/global';
import '../src/module/sw-import-export/component/sw-import-export-activity/global';

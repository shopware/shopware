/**
 * @sw-package framework
 *
 * Makes every `shopware:*` specifier importable from a spec. The Jest config lists this file in `setupFiles`,
 * ahead of every setup file that imports a `shopware:*` module.
 */

import { registerShopwareModuleMocks } from './src/jest-mocks';

registerShopwareModuleMocks(jest);

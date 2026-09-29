/**
 * @sw-package framework
 */

/**
 * Types for the CommonJS bridge (`index.js`), which loads `index.ts` through jiti.
 *
 * Everything here is derived from the TypeScript implementation rather than restated, so the two
 * cannot drift: the previous hand-written copy of `ShopwareSetupTransformResult` had already fallen
 * behind the `ownedBlockNames` / `extendedBlockNames` fields the transform returns.
 */

export { ShopwareSetupTransformError, transformShopwareSetupSfc, validateShopwareSetupSfc } from './index';

export type { ShopwareSetupTransformResult } from './index';

/**
 * Names the filename-inferred transform path used by one Shopware setup SFC.
 */
export type ShopwareSetupTransformMode = import('./index').ShopwareSetupTransformResult['mode'];

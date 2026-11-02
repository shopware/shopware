/**
 * @sw-package framework
 *
 * Generates what `build/shopware-modules` serves: the registry `shopware-modules.json` of every `shopware:*` specifier,
 * and their declarations in `src/shopware-virtual-modules.d.ts`, both checked in.
 * `generate.ts` writes them; `composer admin:generate-shopware-modules` runs it.
 */

/** @public */
export { renderShopwareModules, type GeneratedFiles } from './src/generator';

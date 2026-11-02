/**
 * @sw-package framework
 *
 * Rewrites the checked-in `shopware:*` registry and declarations from the Administration sources.
 *
 *     composer admin:generate-shopware-modules
 */

import { writeShopwareModules } from './src/generator';

writeShopwareModules();

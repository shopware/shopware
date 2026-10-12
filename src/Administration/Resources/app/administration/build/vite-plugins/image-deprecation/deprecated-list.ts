import assets from '../../../src/app/deprecation-registry/definitions/assets';
import type { DeprecationMigration } from '../../../src/app/deprecation-registry/definitions/helpers/types';

// To add an image to the list use the asset migration registry.

/**
 * @private
 * The asset migrations of the deprecation registry, one per group of deprecated files.
 */
export const assetMigrations: DeprecationMigration[] = assets.assetMigrations ?? [];

/**
 * @sw-package framework
 * @private
 */
export default assetMigrations.flatMap((migration) => migration.files ?? []);

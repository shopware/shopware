import assets from '../../../src/app/deprecation-registry/definitions/assets';

type AssetMigration = {
    files?: string[];
};

type AssetRegistry = {
    assetMigrations: AssetMigration[];
};

// To add an image to the list use the asset migration registry.

/**
 * @sw-package framework
 * @private
 */
export default (assets as AssetRegistry).assetMigrations.flatMap((migration) => migration.files ?? []);

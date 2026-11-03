import assetMigrations from '../../../src/app/deprecation-registry/definitions/assets';

/**
 * @sw-package framework
 * @private
 *
 * Every file of the asset migrations in the deprecation registry, relative to the Administration root
 * and starting with `/`. To deprecate an image, add it to an asset migration in
 * `src/app/deprecation-registry/definitions/assets.ts`.
 */
export default assetMigrations.flatMap((migration) => migration.files ?? []);

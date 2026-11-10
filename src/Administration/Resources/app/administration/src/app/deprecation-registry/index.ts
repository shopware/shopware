/**
 * @sw-package framework
 *
 * Catalogue of Administration deprecation codemods. ESLint rules and the
 * `deprecation:migrate` script read it to report and fix deprecated usage.
 */

export { default as componentApiMigrations } from './definitions/components';
export { default as globalApiMigrations } from './definitions/global-api';
export { default as jsApiMigrations } from './definitions/js-api';
export { default as assetMigrations } from './definitions/assets';
export { default as templateBlockMigrations } from './definitions/template-blocks';
export { default as templateEventMigrations } from './definitions/template-events';
export { default as snippetKeyMigrations } from './definitions/snippet-keys';
export { default as packageMigrations } from './definitions/packages';
export { default as apiUsageMigrations } from './definitions/api-usage';

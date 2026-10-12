/**
 * @sw-package framework
 *
 * Catalogue of Administration deprecation codemods. ESLint rules and the
 * `deprecation:migrate` script read it to report and fix deprecated usage.
 */

import componentApiMigrationsDefinition from './definitions/components';
import globalApiMigrationsDefinition from './definitions/global-api';
import jsApiMigrationsDefinition from './definitions/js-api';
import assets from './definitions/assets';
import packageMigrationsDefinition from './definitions/packages';
import snippetKeyMigrationsDefinition from './definitions/snippet-keys';
import templateBlockMigrationsDefinition from './definitions/template-blocks';
import templateEvents from './definitions/template-events';
import type { DeprecationMigration } from './definitions/helpers';

export const componentApiMigrations: DeprecationMigration[] = componentApiMigrationsDefinition;

export const globalApiMigrations: DeprecationMigration[] = globalApiMigrationsDefinition;

export const jsApiMigrations: DeprecationMigration[] = jsApiMigrationsDefinition;

export const assetMigrations: DeprecationMigration[] = assets;

export const templateBlockMigrations: DeprecationMigration[] = templateBlockMigrationsDefinition;

export const templateEventMigrations: DeprecationMigration[] = templateEvents;

export const snippetKeyMigrations: DeprecationMigration[] = snippetKeyMigrationsDefinition;

export const packageMigrations: DeprecationMigration[] = packageMigrationsDefinition;

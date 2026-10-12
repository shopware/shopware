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
import type { DeprecationDefinition } from './definitions/helpers';

const definitionFiles: DeprecationDefinition[] = [
    {
        componentApiMigrations: componentApiMigrationsDefinition,
    },
    {
        globalApiMigrations: globalApiMigrationsDefinition,
    },
    {
        jsApiMigrations: jsApiMigrationsDefinition,
    },
    {
        templateBlockMigrations: templateBlockMigrationsDefinition,
    },
    {
        snippetKeyMigrations: snippetKeyMigrationsDefinition,
    },
    {
        packageMigrations: packageMigrationsDefinition,
    },
    assets,
    templateEvents,
];

export const componentApiMigrations = definitionFiles.flatMap((definition) => {
    return definition.componentApiMigrations ?? [];
});

export const globalApiMigrations = definitionFiles.flatMap((definition) => {
    return definition.globalApiMigrations ?? [];
});

export const jsApiMigrations = definitionFiles.flatMap((definition) => {
    return definition.jsApiMigrations ?? [];
});

export const assetMigrations = definitionFiles.flatMap((definition) => {
    return definition.assetMigrations ?? [];
});

export const templateBlockMigrations = definitionFiles.flatMap((definition) => {
    return definition.templateBlockMigrations ?? [];
});

export const templateEventMigrations = definitionFiles.flatMap((definition) => {
    return definition.templateEventMigrations ?? [];
});

export const snippetKeyMigrations = definitionFiles.flatMap((definition) => {
    return definition.snippetKeyMigrations ?? [];
});

export const packageMigrations = definitionFiles.flatMap((definition) => {
    return definition.packageMigrations ?? [];
});

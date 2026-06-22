/**
 * @sw-package framework
 *
 * Shared deprecation metadata for Administration static analysis, codemods,
 * documentation and dev-mode runtime reporting.
 */

import componentApiMigrationsDefinition from './definitions/components';
import globalApiMigrationsDefinition from './definitions/global-api';
import jsApiMigrationsDefinition from './definitions/js-api';
import assets from './definitions/assets';
import packageMigrationsDefinition from './definitions/packages';
import snippetKeyMigrationsDefinition from './definitions/snippet-keys';
import templateBlockMigrationsDefinition from './definitions/template-blocks';
import templateEvents from './definitions/template-events';
import type { DeprecationDefinition, DeprecationMigration, DeprecationUsage } from './definitions/helpers';

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

const allMigrations = [
    ...componentApiMigrations,
    ...globalApiMigrations,
    ...jsApiMigrations,
    ...assetMigrations,
    ...templateBlockMigrations,
    ...templateEventMigrations,
    ...snippetKeyMigrations,
    ...packageMigrations,
];

const componentMigrationMap = new Map<string, DeprecationMigration>();

componentApiMigrations.forEach((migration) => {
    [
        migration.component,
        migration.replacement,
        migration.handler,
    ].forEach((componentName) => {
        if (!componentName) {
            return;
        }

        componentMigrationMap.set(componentName, migration);
    });
});

export function getComponentApiMigration(componentName: string): DeprecationMigration | null {
    return componentMigrationMap.get(componentName) ?? null;
}

export function getDeprecationMigration(id: string): DeprecationMigration | null {
    return allMigrations.find((migration) => migration.id === id) ?? null;
}

export function getComponentUsageMigration(
    componentName: string,
    predicate: (usage: DeprecationUsage) => boolean,
): { migration: DeprecationMigration; usage: DeprecationUsage } | null {
    const migration = getComponentApiMigration(componentName);

    if (!migration) {
        return null;
    }

    const usage = migration.usage.find(predicate);

    if (!usage) {
        return null;
    }

    return {
        migration,
        usage,
    };
}

export function formatComponentReplacementWarning(componentName: string): string {
    const migration = getComponentApiMigration(componentName);

    if (!migration?.replacement) {
        return `The old usage of "${componentName}" is deprecated.`;
    }

    return (
        `${migration.description} It will be removed in Shopware ${migration.removedIn}. ` +
        `Use "${migration.replacement}" instead.`
    );
}

export function formatComponentUsageWarning(
    componentName: string,
    usage: DeprecationUsage & { migration?: DeprecationMigration },
): string {
    const migration = usage.migration ?? getComponentApiMigration(componentName);
    const apiName = usage.runtimeProp ?? usage.prop ?? usage.from ?? usage.event ?? usage.slot ?? usage.name;

    if (!migration) {
        return `The component "${componentName}" was used with deprecated API "${apiName}".`;
    }

    const replacement = usage.to ? ` Use "${usage.to}" instead.` : '';

    return (
        `The component "${componentName}" was used with deprecated API "${apiName}". ` +
        `It will be removed in Shopware ${migration.removedIn}.${replacement}\n\n${migration.description}`
    );
}

export function formatMigrationDescription(id: string): string {
    const migration = getDeprecationMigration(id);

    if (!migration) {
        return '';
    }

    return `${migration.description} It will be removed in Shopware ${migration.removedIn}.`;
}

export function getRuntimeDeprecatedProps(
    componentName: string,
): Array<DeprecationUsage & { migration: DeprecationMigration }> {
    const migration = getComponentApiMigration(componentName);

    if (!migration) {
        return [];
    }

    return migration.usage
        .filter((usage) => typeof usage.runtime?.detect === 'function' || usage.runtimeProp)
        .map((usage) => {
            return {
                ...usage,
                migration,
            };
        });
}

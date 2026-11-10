/**
 * @sw-package framework
 *
 * One builder per registry category. Each builder only types its migration, so a definition fails to
 * compile when it misses a field its category needs (a component, the asset files, the global API name)
 * or uses a step the category's ESLint rule cannot run.
 */

import type {
    ApiUsageMigration,
    AssetMigration,
    ComponentMigration,
    ComponentMigrationConfig,
    DeprecationReference,
    GlobalApiMigration,
    JsApiMigration,
    PackageMigration,
    SnippetKeyMigration,
    TemplateBlockMigration,
    TemplateEventMigration,
} from './types';

/**
 * Links a migration to its UPGRADE heading; a registry spec checks that the heading exists.
 *
 * @example
 * references: [upgradeGuide('UPGRADE-6.9.md#sw-empty-state-removed')]
 */
export function upgradeGuide(target: string): DeprecationReference {
    return { type: 'upgrade', target };
}

/**
 * Migrates usages of a deprecated component, or of deprecated props, slots and events of a component.
 * `onConvert` steps are limited to elements carrying the "Converted from <component>" comment the
 * conversion leaves, so a hand-written usage of the replacement only gets the `onEveryUsage` steps.
 *
 * @example
 * componentMigration({
 *     id: 'component.sw-loader',
 *     component: 'sw-loader',
 *     replacement: 'mt-loader',
 *     onConvert: [renameComponent({ from: 'sw-loader', to: 'mt-loader' })],
 *     onEveryUsage: [renameProp({ from: 'value', to: 'model-value' })],
 *     ...
 * });
 */
export function componentMigration({
    onConvert = undefined,
    onEveryUsage = [],
    ...migration
}: ComponentMigrationConfig): ComponentMigration {
    const [rename, ...carriedOver] = onConvert ?? [];

    return {
        ...migration,
        usage: [
            ...(rename ? [rename] : []),
            ...carriedOver.map((step) => ({ ...step, onlyAfterConversion: true })),
            ...onEveryUsage,
        ],
    };
}

/**
 * Migrates calls of a deprecated global template API such as `$tc`.
 *
 * @example
 * globalApiMigration({ id: 'global.$tc', api: '$tc', usage: [renameCall({ from: '$tc', to: '$t' })], ... });
 */
export function globalApiMigration(migration: GlobalApiMigration): GlobalApiMigration {
    return migration;
}

/**
 * Migrates deprecated JavaScript APIs: component options and member calls.
 *
 * @example
 * jsApiMigration({ id: 'js-api.meta-info-object', usage: [replaceObjectOption({ from: 'metaInfo', to: '…' })], ... });
 */
export function jsApiMigration(migration: JsApiMigration): JsApiMigration {
    return migration;
}

/**
 * Deprecates asset files; the image-deprecation vite plugin warns on their imports.
 *
 * @example
 * assetMigration({ id: 'asset.x', files: ['/static/img/x.png'], usage: [removeAsset()], ... });
 */
export function assetMigration(migration: AssetMigration): AssetMigration {
    return migration;
}

/**
 * Migrates overrides of removed or renamed Twig blocks.
 *
 * @example
 * templateBlockMigration({ id: 'template-block.x', usage: [removeTemplateBlock({ from: 'sw_x' })], ... });
 */
export function templateBlockMigration(migration: TemplateBlockMigration): TemplateBlockMigration {
    return migration;
}

/**
 * Migrates listeners of renamed component events.
 *
 * @example
 * templateEventMigration({ id: 'template-event.x', usage: [templateEventUsage({ component, from, to })], ... });
 */
export function templateEventMigration(migration: TemplateEventMigration): TemplateEventMigration {
    return migration;
}

/**
 * Reports usage of removed snippet keys.
 *
 * @example
 * snippetKeyMigration({ id: 'snippet.x', usage: [removeSnippetKey({ from: 'global.x' })], ... });
 */
export function snippetKeyMigration(migration: SnippetKeyMigration): SnippetKeyMigration {
    return migration;
}

/**
 * Migrates imports of a renamed npm package.
 *
 * @example
 * packageMigration({ id: 'package.x', usage: [renamePackage({ from: '@a/old', to: '@a/new' })], ... });
 */
export function packageMigration(migration: PackageMigration): PackageMigration {
    return migration;
}

/**
 * Migrates removed or changed Administration APIs that extension code uses: component members in
 * overrides, services, stores, imports and module options. `no-deprecated-api-usage` runs the steps.
 *
 * @example
 * apiUsageMigration({ id: 'api.vuex', usage: [memberUsage({ object: 'Shopware', properties: ['State'] })], ... });
 */
export function apiUsageMigration(migration: ApiUsageMigration): ApiUsageMigration {
    return migration;
}

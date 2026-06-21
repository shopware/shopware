/**
 * @sw-package framework
 *
 * Runtime-facing helpers for compatibility shims that still need dev-mode
 * reporting. Normal components should not import the registry directly.
 */

import { formatMigrationDescription } from './index';

export function getRuntimeMigrationDescription(id: string, fallback = ''): string {
    return formatMigrationDescription(id) || fallback;
}

export function getLegacyTwigBlockDeprecationMessage(blockName: string, componentName: string): string {
    const migrationDescription = getRuntimeMigrationDescription(
        'template-block.legacy-twig-block-shim',
        'uses a legacy Twig override.',
    );

    return (
        `[Shopware Deprecation] Block "${blockName}" in component "${componentName}" ` +
        `${migrationDescription} Migrate to: <sw-block extends="${blockName}">...</sw-block>`
    );
}

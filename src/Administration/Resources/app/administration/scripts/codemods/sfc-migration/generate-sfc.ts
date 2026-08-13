import { ShopwareSetupTransformError, validateShopwareSetupSfc } from '../../../build/vue-setup-transform';
import { TemplateTransformError, transformTemplate } from './transform-template';
import { transformScript } from './transform-script';
import type { MigrationStatus } from './types';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

export type { MigrationStatus } from './types';

export interface MergeResult {
    /** The complete `.vue` SFC string, or `''` for non-migratable components. */
    sfc: string;
    status: MigrationStatus;
    blockers: string[];
    /** Non-fatal issues in the generated output that require manual follow-up. */
    warnings: string[];
    /** Component name from the registration call (`unknown-component` when non-literal). */
    componentName: string;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Combines a component's `.html.twig` and `index.js` sources into a single
 * native setup `.vue` SFC, handling all three migration paths:
 *
 * - **fully-migrated** — a `<script setup>` component that declares its public
 *   override API with `swDefinePublic()`. The build-time transform in
 *   `build/vue-setup-transform` lowers that into the extension runtime.
 * - **partially-migrated** — `<script setup>` whose generated script contains
 *   TODO follow-up comments. Manual follow-up required.
 * - **not-migratable** — returns an empty SFC; nothing is written to disk. A
 *   blocker in the script or the template, and output the transform rejects,
 *   both land here.
 *
 * The `<template>` section always precedes `<script setup>` in the output.
 */
export function mergeComponentFiles(twigContent: string, jsContent: string): MergeResult {
    let templateSection: string;

    try {
        ({ template: templateSection } = transformTemplate(twigContent));
    } catch (err) {
        if (err instanceof TemplateTransformError) {
            return { sfc: '', status: 'not-migratable', blockers: err.blockers, warnings: [], componentName: '' };
        }

        throw err;
    }

    const scriptResult = transformScript(jsContent);
    const { blockers, componentName } = scriptResult;

    if (scriptResult.status === 'not-migratable') {
        return { sfc: '', status: 'not-migratable', blockers, warnings: [], componentName };
    }

    const sfc = [
        templateSection,
        '',
        `<script setup>\n${scriptResult.script}\n</script>`,
    ].join('\n');

    const transformRejection = findTransformRejection(sfc, componentName);
    if (transformRejection) {
        return {
            sfc: '',
            status: 'not-migratable',
            blockers: [
                ...blockers,
                transformRejection,
            ],
            warnings: [],
            componentName,
        };
    }

    // $el has no direct setup equivalent, so it is emitted as a placeholder and
    // keeps the migration partial; surface it as a follow-up warning either way.
    const warnings = sfc.includes('TODO: $el')
        ? ['$el usage detected — replace with a template ref or verify getCurrentInstance() call context']
        : [];

    return { sfc, status: scriptResult.status, blockers, warnings, componentName };
}

// ---------------------------------------------------------------------------
// Internals
// ---------------------------------------------------------------------------

/**
 * Runs the generated SFC through the same transform the build, ESLint, and Volar
 * use, so a component that would be rejected at build time is reported instead
 * of written. The component name is passed as the filename because native setup
 * infers mode and override target from it.
 */
function findTransformRejection(sfc: string, componentName: string): string | null {
    try {
        validateShopwareSetupSfc(sfc, `${componentName}.vue`);

        return null;
    } catch (err) {
        if (err instanceof ShopwareSetupTransformError) {
            return `native setup transform: ${err.message}`;
        }

        throw err;
    }
}

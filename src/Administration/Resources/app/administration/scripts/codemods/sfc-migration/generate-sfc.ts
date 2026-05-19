import { TemplateTransformError, transformTemplate } from './transform-template';
import { transformScript } from './transform-script';
import type { MergeStatus } from './types';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

export type { MergeStatus } from './types';

export interface MergeResult {
    /** The complete `.vue` SFC string, or `''` for non-migratable components. */
    sfc: string;
    status: MergeStatus;
    blockers: string[];
    /** Non-fatal issues in the generated output that require manual follow-up. */
    warnings: string[];
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Combines a component's `.html.twig` and `index.js` sources into a single
 * `.vue` SFC, handling all three migration paths:
 *
 * - **fully-migrated** — `<script setup>` with `createExtendableSetup` so the
 *   component stays extensible via `overrideComponentSetup` after migration.
 * - **partially-migrated** — either plain `<script>` for Options API backoff
 *   components, or `<script setup>` when the generated setup script contains
 *   TODO follow-up comments. Manual follow-up required.
 * - **not-migratable** — returns an empty SFC; nothing is written to disk.
 *   Hard blockers (`render()`) fall into this category.
 *
 * The `<template>` section always precedes `<script …>` in the output.
 */
export function mergeComponentFiles(twigContent: string, jsContent: string): MergeResult {
    let templateSection: string;

    try {
        ({ template: templateSection } = transformTemplate(twigContent));
    } catch (err) {
        if (err instanceof TemplateTransformError) {
            return { sfc: '', status: 'not-migratable', blockers: err.blockers, warnings: [] };
        }

        throw err;
    }

    const scriptResult = transformScript(jsContent);

    if (scriptResult.status === 'not-migratable') {
        return { sfc: '', status: 'not-migratable', blockers: scriptResult.blockers, warnings: [] };
    }

    const scriptWrapper = scriptResult.scriptType === 'setup' ? '<script setup>' : '<script>';

    if (scriptResult.status === 'partially-migratable') {
        const sfc = [
            templateSection,
            '',
            `${scriptWrapper}\n${scriptResult.script}\n</script>`,
        ].join('\n');
        return { sfc, status: 'partially-migrated', blockers: scriptResult.blockers, warnings: [] };
    }

    const sfc = [
        templateSection,
        '',
        `${scriptWrapper}\n${scriptResult.script}\n</script>`,
    ].join('\n');
    const warnings = sfc.includes('TODO: $el')
        ? ['$el usage detected — replace with a template ref or verify getCurrentInstance() call context']
        : [];

    return { sfc, status: 'fully-migrated', blockers: [], warnings };
}

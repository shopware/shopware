import { transformTemplate } from './transform-template';
import { transformScript } from './transform-script';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

/** Distinguishes the three possible outcomes of merging a component's files. */
export type MergeStatus = 'fully-migrated' | 'partially-migrated' | 'not-migratable';

export interface MergeResult {
    /** The complete `.vue` SFC string, or `''` for non-migratable components. */
    sfc: string;
    status: MergeStatus;
    blockers: string[];
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
 * - **partially-migrated** — plain `<script>` preserving the original Options
 *   API for components with soft blockers (mixins, extends).  Manual follow-up
 *   required.
 * - **not-migratable** — returns an empty SFC; nothing is written to disk.
 *   Hard blockers (`render()`) fall into this category.
 *
 * The `<template>` section always precedes `<script …>` in the output.
 */
export function mergeComponentFiles(twigContent: string, jsContent: string): MergeResult {
    const scriptResult = transformScript(jsContent);

    if (scriptResult.status === 'not-migratable') {
        return { sfc: '', status: 'not-migratable', blockers: scriptResult.blockers };
    }

    const templateSection = transformTemplate(twigContent);

    if (scriptResult.status === 'partially-migratable') {
        const sfc = [templateSection, '', `<script>\n${scriptResult.script}\n</script>`].join('\n');

        return { sfc, status: 'partially-migrated', blockers: scriptResult.blockers };
    }

    const sfc = [templateSection, '', `<script setup>\n${scriptResult.script}\n</script>`].join('\n');

    return { sfc, status: 'fully-migrated', blockers: [] };
}

/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import { createAnalysisContext, type AnalysisContext } from './analysis-context';
import { extractComponents, type ComponentExtractionState } from './components';
import { extractShopwareObject } from './shopware-object';
import { EXTRACTOR_VERSION, SNAPSHOT_FORMAT_VERSION, type ApiSnapshot, type ComponentEntry } from './types';

export interface CreateSnapshotOptions {
    adminRoot: string;
    ref: string;
    commit: string | null;
    /** Reused when given, e.g. to share one program between snapshots of the same root. */
    context?: AnalysisContext;
    /**
     * Carries extraction results from one snapshot of a root to the next: read as the previous run,
     * then replaced by this run's. Parts whose sources did not change are reused instead of extracted again.
     */
    reuse?: SnapshotReuse;
}

export interface SnapshotReuse {
    components?: ComponentExtractionState;
}

function compareComponents(a: ComponentEntry, b: ComponentEntry): number {
    if (a.name === b.name) {
        return 0;
    }

    return a.name < b.name ? -1 : 1;
}

export async function createSnapshot({
    adminRoot,
    ref,
    commit,
    context,
    reuse,
}: CreateSnapshotOptions): Promise<ApiSnapshot> {
    if (!fs.existsSync(adminRoot) || !fs.statSync(adminRoot).isDirectory()) {
        throw new Error(`Admin root is not a directory: ${adminRoot}`);
    }

    const analysisContext = context ?? createAnalysisContext(adminRoot);
    const [
        shopwareObject,
        components,
    ] = await Promise.all([
        extractShopwareObject(analysisContext),
        extractComponents(analysisContext, { previous: reuse?.components }),
    ]);

    if (reuse) {
        reuse.components = components.state;
    }

    return {
        formatVersion: SNAPSHOT_FORMAT_VERSION,
        extractorVersion: EXTRACTOR_VERSION,
        ref,
        commit,
        createdAt: new Date().toISOString(),
        shopwareObject: shopwareObject.result,
        components: [...components.result].sort(compareComponents),
        diagnostics: [
            ...shopwareObject.diagnostics,
            ...components.diagnostics,
        ],
    };
}

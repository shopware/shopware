/**
 * @sw-package framework
 */

import type { ApiDiff } from '../diff/types';
import type { ApiSnapshot, ShopwareObjectNode } from '../snapshot/types';
import type { ReportSideSummary, ReportSummary } from './types';

/** Excludes the root, so an empty extraction counts 0. */
function countNodes(node: ShopwareObjectNode): number {
    return node.children.reduce((count, child) => count + 1 + countNodes(child), 0);
}

function summarizeSide(snapshot: ApiSnapshot, fallbackCommit: string | null = null): ReportSideSummary {
    return {
        ref: snapshot.ref,
        commit: snapshot.commit ?? fallbackCommit,
        components: snapshot.components.length,
        objectNodes: countNodes(snapshot.shopwareObject),
        diagnostics: snapshot.diagnostics.length,
    };
}

/** `worktreeCommit` is the checkout's HEAD, recorded when the head is the working tree. */
export function createReportSummary(
    base: ApiSnapshot,
    head: ApiSnapshot,
    diff: ApiDiff,
    worktreeCommit: string | null = null,
): ReportSummary {
    return {
        total: diff.summary.total,
        bySeverity: { ...diff.summary.bySeverity },
        privateBySeverity: { ...diff.summary.privateBySeverity },
        hasBreaking: diff.summary.hasBreaking,
        base: summarizeSide(base),
        head: summarizeSide(head, worktreeCommit),
    };
}

/**
 * @sw-package framework
 */

import type { ApiDiff, PublicSeverity, Severity } from '../diff/types';
import type { ApiSnapshot } from '../snapshot/types';

/** The global that `data.js` of a static report sets before `app.js` runs. */
export const STATIC_REPORT_GLOBAL = '__API_EXPLORER_REPORT__';

export interface StaticReportData {
    base: ApiSnapshot;
    head: ApiSnapshot;
    diff: ApiDiff;
    /** The checkout's HEAD when the head is the working tree. */
    worktreeCommit: string | null;
    /** ISO 8601 */
    generatedAt: string;
}

export interface ReportSideSummary {
    ref: string;
    commit: string | null;
    components: number;
    objectNodes: number;
    diagnostics: number;
}

export interface ReportSummary {
    total: number;
    bySeverity: Record<Severity, number>;
    privateBySeverity: Record<PublicSeverity, number>;
    hasBreaking: boolean;
    base: ReportSideSummary;
    head: ReportSideSummary;
}

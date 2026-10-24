/**
 * @sw-package framework
 */

import { STATIC_REPORT_GLOBAL, type StaticReportData } from '../report/types';

export type { StaticReportData };

/** Set by the `data.js` of a report written by the `report` command. */
export function readStaticReport(): StaticReportData | null {
    return (window as unknown as Partial<Record<string, StaticReportData>>)[STATIC_REPORT_GLOBAL] ?? null;
}

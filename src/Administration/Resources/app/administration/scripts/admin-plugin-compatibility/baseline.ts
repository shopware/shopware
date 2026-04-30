/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import fs from 'fs';
import path from 'path';
import { redactReportValue } from './secrets';
import type { CompatibilityReport } from './reports';

export type BaselineSnapshot = {
    status: CompatibilityReport['summary']['status'];
    failureClass?: CompatibilityReport['summary']['failureClass'];
    shopwareCommit: string;
    commercialRef: string;
    licensePlan: string;
    smokeCommand: string;
    coverageGaps: string[];
    knownUnsupportedCases: string[];
};

export type BaselineComparison = {
    status: 'missing' | 'written' | 'matched' | 'changed';
    path: string;
    differences: string[];
};

export function createBaselineSnapshot(report: CompatibilityReport): BaselineSnapshot {
    return {
        status: report.summary.status,
        failureClass: report.summary.failureClass,
        shopwareCommit: report.commit.shopware,
        commercialRef: report.commercial.ref,
        licensePlan: report.license.plan,
        smokeCommand: report.smokeResult?.command?.command ?? 'not-run',
        coverageGaps: report.coverageGaps,
        knownUnsupportedCases: report.knownUnsupportedCases,
    };
}

export function compareBaseline(report: CompatibilityReport, baseline: BaselineSnapshot, baselinePath: string): BaselineComparison {
    const current = createBaselineSnapshot(report);
    const differences = [
        compareValue('status', baseline.status, current.status),
        compareValue('failureClass', baseline.failureClass ?? 'none', current.failureClass ?? 'none'),
        compareValue('licensePlan', baseline.licensePlan, current.licensePlan),
        compareArray('coverageGaps', baseline.coverageGaps, current.coverageGaps),
        compareArray('knownUnsupportedCases', baseline.knownUnsupportedCases, current.knownUnsupportedCases),
    ].filter((difference): difference is string => difference !== undefined);

    return {
        status: differences.length > 0 ? 'changed' : 'matched',
        path: baselinePath,
        differences,
    };
}

export function readBaseline(baselinePath: string): BaselineSnapshot | undefined {
    if (!fs.existsSync(baselinePath)) {
        return undefined;
    }

    return JSON.parse(fs.readFileSync(baselinePath, 'utf8')) as BaselineSnapshot;
}

export function writeBaselineSnapshot(baselinePath: string, baseline: BaselineSnapshot): void {
    fs.mkdirSync(path.dirname(baselinePath), { recursive: true });
    fs.writeFileSync(baselinePath, `${JSON.stringify(redactReportValue(baseline), null, 2)}\n`);
}

function compareValue(label: string, expected: string, actual: string): string | undefined {
    if (expected === actual) {
        return undefined;
    }

    return `${label}: expected ${expected}, got ${actual}`;
}

function compareArray(label: string, expected: string[], actual: string[]): string | undefined {
    const expectedValue = [...expected].sort().join(',');
    const actualValue = [...actual].sort().join(',');

    return compareValue(label, expectedValue, actualValue);
}

/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import {
    compareBaseline,
    createBaselineSnapshot,
    readBaseline,
    writeBaselineSnapshot,
    type BaselineSnapshot,
} from './baseline';
import type { CompatibilityReport } from './reports';

describe('admin-plugin-compatibility baseline support', () => {
    let temporaryDirectory = '';

    beforeEach(() => {
        temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'admin-plugin-compatibility-baseline-'));
    });

    afterEach(() => {
        fs.rmSync(temporaryDirectory, { recursive: true, force: true });
    });

    it('creates and reads a safe baseline snapshot', () => {
        const baselinePath = path.join(temporaryDirectory, 'baseline.json');
        const snapshot = createBaselineSnapshot(createReport());

        writeBaselineSnapshot(baselinePath, snapshot);

        expect(readBaseline(baselinePath)).toEqual(snapshot);
    });

    it('matches equivalent future reports', () => {
        const report = createReport();
        const baseline = createBaselineSnapshot(report);

        expect(compareBaseline(report, baseline, '/baseline.json')).toEqual({
            status: 'matched',
            path: '/baseline.json',
            differences: [],
        });
    });

    it('marks new failures separately from the baseline', () => {
        const baseline: BaselineSnapshot = {
            ...createBaselineSnapshot(createReport()),
            status: 'passed',
            failureClass: undefined,
            coverageGaps: [],
        };

        expect(compareBaseline(createReport(), baseline, '/baseline.json')).toEqual({
            status: 'changed',
            path: '/baseline.json',
            differences: [
                'status: expected passed, got failed',
                'failureClass: expected none, got runtime',
                'coverageGaps: expected , got sw-unknown-component',
            ],
        });
    });
});

function createReport(): CompatibilityReport {
    return {
        summary: {
            status: 'failed',
            failureClass: 'runtime',
            exitCode: 40,
        },
        environment: {
            generatedAt: '2026-04-30T00:00:00.000Z',
            projectRoot: '/project',
            nodeVersion: 'v24.0.0',
            platform: 'darwin',
        },
        commit: {
            shopware: 'abc1234',
        },
        commercial: {
            path: '/project/custom/plugins/SwagCommercial',
            ref: 'def5678',
        },
        license: {
            host: 'localhost',
            plan: 'beyond',
        },
        commands: [],
        smokeResult: {
            name: 'admin:plugin-compatibility-smoke',
            phase: 'runtime',
            status: 'failed',
        },
        runtimeErrors: [],
        knownUnsupportedCases: [],
        coverageGaps: ['sw-unknown-component'],
        hints: [],
        nextSteps: [],
    };
}

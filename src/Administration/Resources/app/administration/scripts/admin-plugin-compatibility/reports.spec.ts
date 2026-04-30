/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { createCompatibilityReport, renderMarkdownReport, writeCompatibilityReports } from './reports';
import type { CommandResult, RunnerResult } from './types';

describe('admin-plugin-compatibility reports', () => {
    let projectRoot = '';

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'admin-plugin-compatibility-report-'));
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('creates a stable JSON report shape', () => {
        const report = createCompatibilityReport(createResult(), createMetadata(projectRoot));

        expect(report.summary).toEqual({
            status: 'failed',
            failureClass: 'runtime',
            exitCode: 40,
        });
        expect(report.commit).toEqual({
            shopware: 'abc1234',
        });
        expect(report.commercial.ref).toBe('def5678');
        expect(report.coverageGaps).toEqual(['sw-unknown-component']);
    });

    it('adds actionable next steps and hints for a missing license generator', () => {
        const report = createCompatibilityReport(createResult({
            failureClass: 'setup',
            exitCode: 10,
            command: {
                name: 'commercial:license-generator-preflight',
                phase: 'setup',
                stderr: 'commercial-license-generator: command not found',
                exitCode: 127,
            },
        }), createMetadata(projectRoot));

        expect(report.nextSteps).toContain(
            'Install commercial-license-generator on PATH or pass --commercial-license-generator with the executable command, then rerun the workflow.',
        );
        expect(report.hints).toContain(
            'The missing license generator is classified as setup because it is a local prerequisite, even though license generation is the next functional phase.',
        );
    });

    it('adds targeted hints for host memory and database failures', () => {
        const memoryReport = createCompatibilityReport(createResult({
            failureClass: 'setup',
            exitCode: 10,
            command: {
                name: 'commercial:plugin-refresh',
                phase: 'setup',
                stderr: 'PHP Fatal error: Allowed memory size of 134217728 bytes exhausted',
                exitCode: 255,
            },
        }), createMetadata(projectRoot));
        const databaseReport = createCompatibilityReport(createResult({
            failureClass: 'setup',
            exitCode: 10,
            command: {
                name: 'commercial:plugin-refresh',
                phase: 'setup',
                stderr: 'SQLSTATE[HY000] [2002] No such file or directory',
                exitCode: 1,
            },
        }), createMetadata(projectRoot));

        expect(memoryReport.hints).toContain(
            'The host PHP memory limit was exhausted. Retry with --commercial-console-command "php -d memory_limit=-1 bin/console" or use the Docker console command.',
        );
        expect(databaseReport.hints).toContain(
            'The host console command could not reach the Docker-backed database. Retry with --commercial-console-command "docker compose exec web bin/console".',
        );
    });

    it('adds a targeted hint for missing internal dev licenses', () => {
        const report = createCompatibilityReport(createResult({
            failureClass: 'license',
            exitCode: 20,
            command: {
                name: 'commercial:license-generator',
                phase: 'license',
                stderr: 'Bundled commercial-license-generator fallback cannot create signed Commercial licenses.',
                exitCode: 1,
            },
        }), createMetadata(projectRoot));

        expect(report.hints).toContain(
            'The bundled license wrapper cannot create signed Commercial licenses. Install the internal commercial-license-generator on PATH, pass it with --commercial-license-generator, or provide a downloaded dev license JSON/plain key file via --commercial-license-key-file.',
        );
    });

    it('adds a targeted hint when bundled fallback preflight fails before plugin mutations', () => {
        const report = createCompatibilityReport(createResult({
            failureClass: 'setup',
            exitCode: 10,
            command: {
                name: 'commercial:license-generator-preflight',
                phase: 'setup',
                stdout: 'commercial-license-generator local compatibility wrapper',
                exitCode: 0,
            },
            stepMessage: 'Bundled commercial-license-generator fallback requires --commercial-license-key-file or ADMIN_PLUGIN_COMPATIBILITY_COMMERCIAL_LICENSE_KEY_FILE before plugin mutations.',
        }), createMetadata(projectRoot));

        expect(report.hints).toContain(
            'The bundled license wrapper cannot create signed Commercial licenses. Install the internal commercial-license-generator on PATH, pass it with --commercial-license-generator, or provide a downloaded dev license JSON/plain key file via --commercial-license-key-file.',
        );
    });


    it('renders Markdown with required sections and redacted values', () => {
        const markdown = renderMarkdownReport(createCompatibilityReport(createResult(), createMetadata(projectRoot)));

        expect(markdown).toContain('## Summary');
        expect(markdown).toContain('## Commands');
        expect(markdown).toContain('## Coverage Gaps');
        expect(markdown).toContain('## Hints');
        expect(markdown).toContain('[REDACTED]');
        expect(markdown).not.toContain('secret-token-value');
    });

    it('writes redacted JSON and Markdown reports', () => {
        const reportResult = writeCompatibilityReports(createResult(), projectRoot, {
            baselineFile: 'var/admin-plugin-compatibility/baseline/commercial.json',
            writeBaseline: false,
        });

        expect(fs.existsSync(reportResult.paths.json)).toBe(true);
        expect(fs.existsSync(reportResult.paths.markdown)).toBe(true);
        expect(fs.readFileSync(reportResult.paths.json, 'utf8')).not.toContain('secret-token-value');
        expect(fs.readFileSync(reportResult.paths.markdown, 'utf8')).not.toContain('secret-token-value');
        expect(reportResult.baseline.status).toBe('missing');
    });
});

function createResult(overrides: {
    failureClass?: RunnerResult['failureClass'];
    exitCode?: number;
    command?: Partial<CommandResult>;
    stepMessage?: string;
} = {}): RunnerResult {
    const command = createCommandResult(overrides.command);

    return {
        status: 'failed',
        exitCode: overrides.exitCode ?? 40,
        failureClass: overrides.failureClass ?? 'runtime',
        profile: 'commercial',
        reportDir: 'var/admin-plugin-compatibility/reports/',
        commercialPath: path.join(projectRootSafe(), 'custom/plugins/SwagCommercial'),
        licenseHost: 'localhost',
        licensePlan: 'beyond',
        coverageGaps: ['sw-unknown-component'],
        ciGuardVariables: [],
        steps: [{
            name: command.name,
            phase: command.phase,
            status: 'failed',
            message: overrides.stepMessage,
            command,
        }],
        commands: [command],
    };
}

function createCommandResult(overrides: Partial<CommandResult> = {}): CommandResult {
    return {
        name: 'admin:plugin-compatibility-smoke',
        phase: 'runtime',
        cwd: '/project/tests/acceptance',
        command: 'npx playwright test --config playwright.admin-plugin-compatibility.config.ts --token secret-token-value',
        stdout: 'licenseKey: secret-token-value',
        stderr: 'runtime error secret-token-value',
        exitCode: 1,
        durationMs: 10,
        startedAt: '2026-04-30T00:00:00.000Z',
        ...overrides,
    };
}

function createMetadata(projectRoot: string) {
    return {
        generatedAt: '2026-04-30T00:00:00.000Z',
        projectRoot,
        nodeVersion: 'v24.0.0',
        platform: 'darwin',
        shopwareCommit: 'abc1234',
        commercialRef: 'def5678',
    };
}

function projectRootSafe(): string {
    return '/project';
}

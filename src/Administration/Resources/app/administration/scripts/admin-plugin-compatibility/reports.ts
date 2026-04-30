/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import {
    compareBaseline,
    createBaselineSnapshot,
    readBaseline,
    writeBaselineSnapshot,
    type BaselineComparison,
} from './baseline';
import { redactReportValue } from './secrets';
import type { CommandResult, RunnerResult, StepResult } from './types';

export type ReportMetadata = {
    generatedAt: string;
    projectRoot: string;
    nodeVersion: string;
    platform: string;
    shopwareCommit: string;
    commercialRef: string;
};

export type CompatibilityReport = {
    summary: {
        status: RunnerResult['status'];
        failureClass?: RunnerResult['failureClass'];
        exitCode: number;
    };
    environment: Pick<ReportMetadata, 'generatedAt' | 'projectRoot' | 'nodeVersion' | 'platform'>;
    commit: {
        shopware: string;
    };
    commercial: {
        path: string;
        ref: string;
    };
    license: {
        host: string;
        plan: string;
    };
    commands: CommandResult[];
    buildResult?: StepResult;
    smokeResult?: StepResult;
    runtimeErrors: string[];
    knownUnsupportedCases: string[];
    coverageGaps: string[];
    hints: string[];
    baseline?: BaselineComparison;
    nextSteps: string[];
};

type ReportWriteOptions = {
    baselineFile: string;
    writeBaseline: boolean;
};

export function writeCompatibilityReports(
    result: RunnerResult,
    projectRoot: string,
    options: ReportWriteOptions,
): { paths: { json: string; markdown: string }; baseline: BaselineComparison } {
    const metadata = createReportMetadata(projectRoot, result.commercialPath);
    const report = createCompatibilityReport(result, metadata);
    const safeTimestamp = metadata.generatedAt.replace(/[:.]/g, '-');
    const reportDirectory = path.isAbsolute(result.reportDir) ? result.reportDir : path.join(projectRoot, result.reportDir);
    const baselinePath = path.isAbsolute(options.baselineFile) ? options.baselineFile : path.join(projectRoot, options.baselineFile);
    const jsonPath = path.join(reportDirectory, `admin-plugin-compatibility-${safeTimestamp}.json`);
    const markdownPath = path.join(reportDirectory, `admin-plugin-compatibility-${safeTimestamp}.md`);
    const baseline = updateBaseline(report, baselinePath, options.writeBaseline);

    report.baseline = baseline;

    fs.mkdirSync(reportDirectory, { recursive: true });
    fs.writeFileSync(jsonPath, `${JSON.stringify(redactReportValue(report), null, 2)}\n`);
    fs.writeFileSync(markdownPath, renderMarkdownReport(report));

    return {
        paths: {
            json: jsonPath,
            markdown: markdownPath,
        },
        baseline,
    };
}

export function createCompatibilityReport(result: RunnerResult, metadata: ReportMetadata): CompatibilityReport {
    return {
        summary: {
            status: result.status,
            failureClass: result.failureClass,
            exitCode: result.exitCode,
        },
        environment: {
            generatedAt: metadata.generatedAt,
            projectRoot: metadata.projectRoot,
            nodeVersion: metadata.nodeVersion,
            platform: metadata.platform,
        },
        commit: {
            shopware: metadata.shopwareCommit,
        },
        commercial: {
            path: result.commercialPath,
            ref: metadata.commercialRef,
        },
        license: {
            host: result.licenseHost,
            plan: result.licensePlan,
        },
        commands: result.commands,
        buildResult: result.steps.find((step) => step.name === 'admin:build'),
        smokeResult: result.steps.find((step) => step.name === 'admin:plugin-compatibility-smoke'),
        runtimeErrors: collectRuntimeErrors(result.commands),
        knownUnsupportedCases: [],
        coverageGaps: result.coverageGaps,
        hints: createFailureHints(result),
        nextSteps: createNextSteps(result),
    };
}

export function renderMarkdownReport(report: CompatibilityReport): string {
    const redactedReport = redactReportValue(report) as CompatibilityReport;

    return `${[
        '# Admin Plugin Compatibility Report',
        '',
        '## Summary',
        `- Status: ${redactedReport.summary.status}`,
        `- Failure class: ${redactedReport.summary.failureClass ?? 'none'}`,
        `- Exit code: ${redactedReport.summary.exitCode}`,
        '',
        '## Environment',
        `- Generated at: ${redactedReport.environment.generatedAt}`,
        `- Project root: ${redactedReport.environment.projectRoot}`,
        `- Node: ${redactedReport.environment.nodeVersion}`,
        `- Platform: ${redactedReport.environment.platform}`,
        '',
        '## Commit',
        `- Shopware: ${redactedReport.commit.shopware}`,
        '',
        '## Commercial',
        `- Path: ${redactedReport.commercial.path}`,
        `- Ref: ${redactedReport.commercial.ref}`,
        '',
        '## License',
        `- Host: ${redactedReport.license.host}`,
        `- Plan: ${redactedReport.license.plan}`,
        '',
        '## Commands',
        renderCommands(redactedReport.commands),
        '',
        '## Build Result',
        renderStep(redactedReport.buildResult),
        '',
        '## Smoke Result',
        renderStep(redactedReport.smokeResult),
        '',
        '## Runtime Errors',
        renderList(redactedReport.runtimeErrors),
        '',
        '## Known Unsupported Cases',
        renderList(redactedReport.knownUnsupportedCases),
        '',
        '## Coverage Gaps',
        renderList(redactedReport.coverageGaps),
        '',
        '## Hints',
        renderList(redactedReport.hints),
        '',
        '## Baseline',
        renderBaseline(redactedReport.baseline),
        '',
        '## Next Steps',
        renderList(redactedReport.nextSteps),
        '',
    ].join('\n')}\n`;
}

function updateBaseline(report: CompatibilityReport, baselinePath: string, writeBaseline: boolean): BaselineComparison {
    if (writeBaseline) {
        writeBaselineSnapshot(baselinePath, createBaselineSnapshot(report));

        return {
            status: 'written',
            path: baselinePath,
            differences: [],
        };
    }

    const baseline = readBaseline(baselinePath);

    if (!baseline) {
        return {
            status: 'missing',
            path: baselinePath,
            differences: [],
        };
    }

    return compareBaseline(report, baseline, baselinePath);
}

function createReportMetadata(projectRoot: string, commercialPath: string): ReportMetadata {
    return {
        generatedAt: new Date().toISOString(),
        projectRoot,
        nodeVersion: process.version,
        platform: process.platform,
        shopwareCommit: getGitRef(projectRoot),
        commercialRef: getGitRef(commercialPath),
    };
}

function getGitRef(cwd: string): string {
    try {
        return execFileSync('git', ['-C', cwd, 'rev-parse', '--short', 'HEAD'], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        }).trim();
    } catch {
        return 'unknown';
    }
}

function collectRuntimeErrors(commands: CommandResult[]): string[] {
    const smokeCommand = commands.find((command) => command.name === 'admin:plugin-compatibility-smoke');

    if (!smokeCommand || smokeCommand.exitCode === 0) {
        return [];
    }

    return [smokeCommand.stderr, smokeCommand.stdout]
        .join('\n')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function createNextSteps(result: RunnerResult): string[] {
    if (result.status === 'passed' && result.coverageGaps.length === 0) {
        return ['Attach the Markdown report to the related Administration migration PR.'];
    }

    const nextSteps: string[] = [];

    if (hasMissingLicenseGenerator(result.commands)) {
        nextSteps.push('Install commercial-license-generator on PATH or pass --commercial-license-generator with the executable command, then rerun the workflow.');
    } else if (result.failureClass) {
        nextSteps.push(`Fix the ${result.failureClass} failure and rerun composer admin:plugin-compatibility -- --profile commercial.`);
    }

    if (result.coverageGaps.length > 0) {
        nextSteps.push('Add smoke mappings for the listed component coverage gaps or document why no local smoke is possible.');
    }

    return nextSteps;
}

function createFailureHints(result: RunnerResult): string[] {
    const hints: string[] = [];
    const failedCommands = result.commands.filter((command) => command.exitCode !== 0);
    const failedSteps = result.steps.filter((step) => step.status === 'failed');

    if (hasMissingLicenseGenerator(result.commands)) {
        hints.push('The missing license generator is classified as setup because it is a local prerequisite, even though license generation is the next functional phase.');
        hints.push('For a Docker-based shop with the generator on the host, keep --commercial-console-command "docker compose exec web bin/console" and pass --commercial-license-generator only when the binary has a non-default name or path.');
        hints.push('For a Docker-based shop with the generator inside the web container, pass --commercial-license-generator "docker compose exec web commercial-license-generator" together with --commercial-console-command "docker compose exec web bin/console".');
    }

    if (failedCommands.some(hasPhpMemoryExhaustion)) {
        hints.push('The host PHP memory limit was exhausted. Retry with --commercial-console-command "php -d memory_limit=-1 bin/console" or use the Docker console command.');
    }

    if (failedCommands.some(hasHostDatabaseConnectionFailure)) {
        hints.push('The host console command could not reach the Docker-backed database. Retry with --commercial-console-command "docker compose exec web bin/console".');
    }

    if (failedCommands.some(hasBundledGeneratorMissingDevLicense) || failedSteps.some(hasBundledGeneratorMissingDevLicenseStep)) {
        hints.push('The bundled license wrapper cannot create signed Commercial licenses. Install the internal commercial-license-generator on PATH, pass it with --commercial-license-generator, or provide a downloaded dev license JSON/plain key file via --commercial-license-key-file.');
    }

    return hints;
}

function hasMissingLicenseGenerator(commands: CommandResult[]): boolean {
    return commands.some((command) => {
        return (command.name === 'commercial:license-generator' || command.name === 'commercial:license-generator-preflight') &&
            (command.exitCode === 127 || /(command not found|not found|ENOENT|executable file not found)/i.test(getCommandOutput(command)));
    });
}

function hasPhpMemoryExhaustion(command: CommandResult): boolean {
    return /(Allowed memory size .* exhausted|memory_limit|PHP Fatal error:.*memory)/i.test(getCommandOutput(command));
}

function hasHostDatabaseConnectionFailure(command: CommandResult): boolean {
    return /(SQLSTATE\[HY000\]\s*\[2002\]|Access denied for user .*@.*localhost|Connection refused.*(3306|mysql|database)|No such file or directory.*mysql)/i.test(getCommandOutput(command));
}

function hasBundledGeneratorMissingDevLicense(command: CommandResult): boolean {
    return /Bundled commercial-license-generator fallback cannot create signed Commercial licenses/i.test(getCommandOutput(command));
}

function hasBundledGeneratorMissingDevLicenseStep(step: StepResult): boolean {
    return /Bundled commercial-license-generator fallback requires|Commercial dev license key file was not found/i.test(step.message ?? '');
}

function getCommandOutput(command: CommandResult): string {
    return `${command.stderr}\n${command.stdout}`;
}

function renderCommands(commands: CommandResult[]): string {
    if (commands.length === 0) {
        return '- None';
    }

    return commands.map((command) => {
        return `- ${command.name}: ${command.exitCode ?? 'null'} (${command.durationMs} ms) \`${command.command}\``;
    }).join('\n');
}

function renderStep(step: StepResult | undefined): string {
    if (!step) {
        return '- Not run';
    }

    return `- ${step.name}: ${step.status}${step.message ? ` (${step.message})` : ''}`;
}

function renderList(items: string[]): string {
    if (items.length === 0) {
        return '- None';
    }

    return items.map((item) => `- ${item}`).join('\n');
}

function renderBaseline(baseline: BaselineComparison | undefined): string {
    if (!baseline) {
        return '- Not checked';
    }

    return [
        `- Status: ${baseline.status}`,
        `- Path: ${baseline.path}`,
        `- Differences: ${baseline.differences.length > 0 ? baseline.differences.join('; ') : 'none'}`,
    ].join('\n');
}

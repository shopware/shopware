/**
 * @sw-package framework
 *
 * Per-extension findings baseline (PHPStan-style). A committed
 * `.shopware-admin-baseline.json` at the plugin root records the findings that
 * existed when the plugin adopted the tooling, so the check can report them
 * separately and fail only on *new* findings. Matching keys drop line and
 * column (file + code|rule + message) so a recorded finding survives unrelated
 * line drift; duplicates are matched by count. Only writable `custom/plugins`
 * extensions carry a baseline — vendor findings are already non-fatal.
 */

import fs from 'fs';
import path from 'path';
import { DEFAULT_TOOLING_COMMANDS, GENERATED_MARKER, toPosix, writeManagedFile } from './shared';
import type { ToolingCommands, WriteResult } from './shared';

export const BASELINE_FILE_NAME = '.shopware-admin-baseline.json';

export interface TypeScriptFinding {
    /** Path as the tool printed it (project-root-relative). */
    file: string;
    /** Diagnostic code, e.g. "TS2322". */
    code: string;
    message: string;
}

export interface EslintFinding {
    /** Path as the tool printed it (project-root-relative after relativizeToolOutput). */
    file: string;
    /** Rule id, or "" for a rule-less problem (e.g. a parsing error). */
    rule: string;
    message: string;
    severity: 'error' | 'warning';
}

export interface BaselineTsEntry {
    file: string;
    code: string;
    message: string;
    count: number;
}

export interface BaselineEslintEntry {
    file: string;
    rule: string;
    message: string;
    count: number;
}

export interface FindingsBaseline {
    version: 1;
    typescript: BaselineTsEntry[];
    /** Findings from the dedicated spec type-check program. */
    typescriptSpecs: BaselineTsEntry[];
    eslint: BaselineEslintEntry[];
}

export interface BaselineSplit<F> {
    /** Findings not covered by the baseline — these drive the exit code. */
    newFindings: F[];
    /** Findings matched against a baseline entry, so the report can list what it suppressed. */
    baselinedFindings: F[];
    /** Baseline entries that matched nothing this run (prunable). */
    staleCount: number;
    /**
     * The structured parse disagreed with the regex counter, so the baseline
     * was not applied and every finding is reported as new. This keeps a parser
     * bug from silently greening real findings.
     */
    parseMismatch: boolean;
}

interface BaselineProject {
    basePath: string;
    vendor: boolean;
}

/**
 * A baseline is committed plugin data, so only a writable `custom/plugins`
 * extension can carry one: vendor extensions are not ours to write to, and an
 * in-repo bundle (`src/Storefront`, …) must not collect per-developer debt
 * files. Callers gate on this rather than on a null path, so "cannot hold a
 * baseline" is a stated condition instead of a silent absence.
 */
export function canHoldBaseline(project: BaselineProject): boolean {
    return !project.vendor && project.basePath.startsWith('custom/plugins/');
}

/** Absolute path to a project's baseline file, or null when the project cannot carry one. */
export function baselineFilePath(projectRoot: string, project: BaselineProject): string | null {
    if (!canHoldBaseline(project)) {
        return null;
    }

    return path.join(projectRoot, project.basePath, BASELINE_FILE_NAME);
}

/** Defensive disk read — null on absence, garbage, or a version mismatch; missing streams normalize to empty. */
export function readBaseline(projectRoot: string, project: BaselineProject): FindingsBaseline | null {
    const file = baselineFilePath(projectRoot, project);

    if (!file) {
        return null;
    }

    try {
        const parsed = JSON.parse(fs.readFileSync(file, 'utf8')) as Partial<FindingsBaseline>;

        if (!parsed || parsed.version !== 1) {
            return null;
        }

        return {
            version: 1,
            typescript: Array.isArray(parsed.typescript) ? parsed.typescript : [],
            typescriptSpecs: Array.isArray(parsed.typescriptSpecs) ? parsed.typescriptSpecs : [],
            eslint: Array.isArray(parsed.eslint) ? parsed.eslint : [],
        };
    } catch {
        return null;
    }
}

/**
 * Tool output prints project-root-relative paths; baseline entries are stored
 * relative to the plugin root so they travel with the plugin. Strip the base
 * path to compare on the same footing.
 */
function toBaselineRelative(file: string, basePath: string): string {
    const posix = toPosix(file);
    const prefix = `${basePath}/`;

    return posix.startsWith(prefix) ? posix.slice(prefix.length) : posix;
}

function coreDiff<F, E extends { count: number }>(
    findings: F[],
    entries: E[],
    findingKey: (finding: F) => string,
    entryKey: (entry: E) => string,
): Omit<BaselineSplit<F>, 'parseMismatch'> {
    const remaining = new Map<string, number>();

    for (const entry of entries) {
        const key = entryKey(entry);

        remaining.set(key, (remaining.get(key) ?? 0) + Math.max(0, entry.count));
    }

    const newFindings: F[] = [];
    const baselinedFindings: F[] = [];

    for (const finding of findings) {
        const key = findingKey(finding);
        const left = remaining.get(key) ?? 0;

        if (left > 0) {
            remaining.set(key, left - 1);
            baselinedFindings.push(finding);
        } else {
            newFindings.push(finding);
        }
    }

    let staleCount = 0;

    for (const left of remaining.values()) {
        staleCount += left;
    }

    return { newFindings, baselinedFindings, staleCount };
}

function tsFindingKey(finding: TypeScriptFinding, basePath: string): string {
    return `${toBaselineRelative(finding.file, basePath)}\u0000${finding.code}\u0000${finding.message}`;
}

function tsEntryKey(entry: BaselineTsEntry): string {
    return `${entry.file}\u0000${entry.code}\u0000${entry.message}`;
}

function eslintFindingKey(finding: EslintFinding, basePath: string): string {
    return `${toBaselineRelative(finding.file, basePath)}\u0000${finding.rule}\u0000${finding.message}`;
}

function eslintEntryKey(entry: BaselineEslintEntry): string {
    return `${entry.file}\u0000${entry.rule}\u0000${entry.message}`;
}

/**
 * Splits TypeScript findings into new vs baselined. `regexCount` is the
 * independent counter; a disagreement means the parser missed a finding, so the
 * baseline is not applied (every finding is reported as new).
 */
export function diffTypeScript(
    findings: TypeScriptFinding[],
    entries: BaselineTsEntry[],
    basePath: string,
    regexCount: number,
): BaselineSplit<TypeScriptFinding> {
    if (findings.length !== regexCount) {
        return { newFindings: [...findings], baselinedFindings: [], staleCount: 0, parseMismatch: true };
    }

    return {
        ...coreDiff(findings, entries, (finding) => tsFindingKey(finding, basePath), tsEntryKey),
        parseMismatch: false,
    };
}

/**
 * Splits ESLint findings into new vs baselined. Only error-severity findings
 * are baselined (warnings never fail the check), but the parse count is checked
 * against all problems so the regex-counter safety net stays honest.
 */
export function diffEslint(
    findings: EslintFinding[],
    entries: BaselineEslintEntry[],
    basePath: string,
    regexCount: number,
): BaselineSplit<EslintFinding> {
    const errors = findings.filter((finding) => finding.severity === 'error');

    if (findings.length !== regexCount) {
        return { newFindings: errors, baselinedFindings: [], staleCount: 0, parseMismatch: true };
    }

    return {
        ...coreDiff(errors, entries, (finding) => eslintFindingKey(finding, basePath), eslintEntryKey),
        parseMismatch: false,
    };
}

function aggregateTs(findings: TypeScriptFinding[], basePath: string): BaselineTsEntry[] {
    const entries = new Map<string, BaselineTsEntry>();

    for (const finding of findings) {
        const file = toBaselineRelative(finding.file, basePath);
        const key = `${file} ${finding.code} ${finding.message}`;
        const existing = entries.get(key);

        if (existing) {
            existing.count += 1;
        } else {
            entries.set(key, { file, code: finding.code, message: finding.message, count: 1 });
        }
    }

    return [...entries.values()].sort(
        (left, right) =>
            left.file.localeCompare(right.file) ||
            left.code.localeCompare(right.code) ||
            left.message.localeCompare(right.message),
    );
}

function aggregateEslint(findings: EslintFinding[], basePath: string): BaselineEslintEntry[] {
    const entries = new Map<string, BaselineEslintEntry>();

    for (const finding of findings) {
        const file = toBaselineRelative(finding.file, basePath);
        const key = `${file} ${finding.rule} ${finding.message}`;
        const existing = entries.get(key);

        if (existing) {
            existing.count += 1;
        } else {
            entries.set(key, { file, rule: finding.rule, message: finding.message, count: 1 });
        }
    }

    return [...entries.values()].sort(
        (left, right) =>
            left.file.localeCompare(right.file) ||
            left.rule.localeCompare(right.rule) ||
            left.message.localeCompare(right.message),
    );
}

/**
 * Builds a baseline from the current findings — grouped by identity with a
 * count, stored plugin-relative, and canonically sorted so committed baselines
 * do not churn across machines or branches. Only error-severity ESLint findings
 * are recorded (warnings never fail the check).
 */
export function buildBaseline(
    findings: { typescript: TypeScriptFinding[]; typescriptSpecs: TypeScriptFinding[]; eslint: EslintFinding[] },
    basePath: string,
): FindingsBaseline {
    return {
        version: 1,
        typescript: aggregateTs(findings.typescript, basePath),
        typescriptSpecs: aggregateTs(findings.typescriptSpecs, basePath),
        eslint: aggregateEslint(
            findings.eslint.filter((finding) => finding.severity === 'error'),
            basePath,
        ),
    };
}

/**
 * Serializes a baseline with the ownership marker as the first property (line 2
 * of the pretty JSON), so JSON.parse ignores it while isGeneratedContent still
 * recognizes the file as tool-owned — a human-written baseline lacking the
 * marker is then protected as a conflict instead of being overwritten.
 */
export function serializeBaseline(baseline: FindingsBaseline, commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS): string {
    return `${JSON.stringify(
        {
            '//': `${GENERATED_MARKER} — recorded findings; refresh with ${commands.check} -- --update-baseline`,
            ...baseline,
        },
        null,
        4,
    )}\n`;
}

/** Writes a project's baseline, or null when the project cannot carry one (vendor / non-custom-plugins). */
export function writeBaselineFile(
    projectRoot: string,
    project: BaselineProject,
    baseline: FindingsBaseline,
    dryRun = false,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): WriteResult | null {
    const file = baselineFilePath(projectRoot, project);

    if (!file) {
        return null;
    }

    return writeManagedFile(file, serializeBaseline(baseline, commands), dryRun);
}

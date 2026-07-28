/**
 * @sw-package framework
 *
 * Data model for the extension check runner — the result and option types
 * shared between the orchestrator, the pipeline primitives, and the tool
 * runners. Kept in a leaf module so those can depend on the shapes without
 * importing the orchestrator (and its CLI entrypoint) back.
 */

import type { AdministrationTarget, ExtensionToolingProject, ModeResolution, SkippedTarget } from './shared';
import type { EslintFinding, TypeScriptFinding } from './baseline';

export type ToolStatus = 'passed' | 'failed' | 'unmanaged' | 'no-files' | 'blocked' | 'tooling-error';

export interface ToolRunResult {
    status: ToolStatus;
    output: string;
    /** Wall-clock time of this tool's stage, including time queued behind other tool runs of the same check. */
    durationMs: number;
    /** Total findings the tool reported (native count, never baseline-adjusted). */
    findings: number;
    /** Findings not covered by the baseline — the count that drives the exit code. */
    newFindings?: number;
    /** Findings suppressed by a matching baseline entry. */
    baselinedFindings?: number;
    /** Baseline entries that matched nothing this run (prunable via --update-baseline). */
    staleBaseline?: number;
    /** Identities of the new findings, for the report to point at them among the baselined ones. */
    newFindingRefs?: Array<{ file: string; code: string }>;
    /** Structured diagnostics retained for safe aggregate baselines across multiple programs. */
    typeScriptFindings?: TypeScriptFinding[];
    eslintFindings?: EslintFinding[];
    /** Whether any native counter disagreed with its structured parser. */
    parseMismatch?: boolean;
    /**
     * TypeScript diagnostics whose file lies outside the extension root — they
     * come from the shared type surface (or a global the extension pulled into
     * it), are always fatal, and are never recorded in the extension baseline.
     */
    surfaceDiagnostics?: number;
}

export interface AdministrationTargetCoverage {
    target: AdministrationTarget;
    /** Effective runtime config; identical canonical paths are executed once. */
    runtimeConfig: string;
    /** Dedicated spec config for this target. */
    specConfig: string;
    /** Effective ESLint config; identical canonical paths are executed once. */
    eslintConfig: string;
}

export interface ExtensionCheckResult {
    project: ExtensionToolingProject;
    tsResolution: ModeResolution;
    eslintResolution: ModeResolution;
    typescript: ToolRunResult;
    /** The dedicated spec type-check program (jest types, spec files only). */
    typescriptSpecs: ToolRunResult;
    eslint: ToolRunResult;
    /** Reproduction commands for the tool runs that actually happened. */
    commands: { typescript?: string[]; typescriptSpecs?: string[]; eslint?: string[] };
    /** Target/config routing used by this aggregate extension result. */
    coverage: AdministrationTargetCoverage[];
    /** Targets whose own config kept a tool from covering them, regardless of the run status. */
    skippedTargets?: SkippedTarget[];
}

export interface CheckExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    only?: string | string[];
    strictVendor?: boolean;
    maxWorkers?: number;
    /** Forward --fix to ESLint (never to vue-tsc). */
    fix?: boolean;
    /** Names passed literally via --only — vendor extensions are only fixed when named here. */
    explicitOnly?: string[];
    /** Record the current findings as the baseline instead of failing on them. */
    updateBaseline?: boolean;
    /** Fail (exit 1) when a writable extension's tool run was skipped/blocked, not only on findings. */
    failOnSkipped?: boolean;
}

export interface CheckExtensionsResult {
    results: ExtensionCheckResult[];
    fatalDiagnostics: string[];
    warnings: string[];
    /** Human lines describing baselines written under --update-baseline. */
    baselineUpdates: string[];
    exitCode: number;
}

export type Limiter = <T>(job: () => Promise<T>) => Promise<T>;

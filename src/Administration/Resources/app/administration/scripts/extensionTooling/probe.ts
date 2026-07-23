/**
 * @sw-package framework
 *
 * Mode resolution for extension-owned configs. Two layers:
 *
 * 1. Static analysis — synchronous, no process spawns, safe for setup: parse
 *    the config and walk its `extends` chain / import specifiers. A fast
 *    best-guess that setup renders.
 * 2. Live probes — asynchronous `tsc --showConfig` / `eslint --print-config`
 *    runs, the authority, executed by the check command.
 */

import fs from 'fs';
import path from 'path';
import ts from 'typescript';
import { PROCESS_TIMEOUT_MS, runCommand } from './probe-command';
import type { CommandResult } from './probe-command';
import { SHIM_DIR_NAME } from './shared';
import type { AdministrationTarget, ModeReason, ModeResolution } from './shared';

// The child-process runner lives in ./probe-command; these bindings are
// re-exported so ./probe stays the single import surface callers and specs use.
export { PROCESS_TIMEOUT_MS, runCommand };
export type { CommandResult };

/** How deep an `extends` chain is followed before giving up. */
const MAX_EXTENDS_DEPTH = 10;

export interface StaticConfigAnalysis {
    /** The `extends` chain reaches the shipped preset or a generated bridge. */
    reachesPreset: boolean;
    /** The config declares its own `files` (replaces the bridge's injection). */
    declaresFiles: boolean;
    /** The config declares its own `paths` (replaces the preset's wholesale). */
    declaresPaths: boolean;
    parseError?: string;
}

function isPresetPath(configPath: string): boolean {
    const posixPath = configPath.split(path.sep).join('/');

    return posixPath.includes(`/${SHIM_DIR_NAME}/`) || posixPath.endsWith('extension-tooling/tsconfig.base.json');
}

function parseTsconfig(configPath: string): { config?: Record<string, unknown>; error?: string } {
    let text: string;

    try {
        text = fs.readFileSync(configPath, 'utf8');
    } catch (error) {
        return { error: error instanceof Error ? error.message : String(error) };
    }

    const parsed = ts.parseConfigFileTextToJson(configPath, text);

    if (parsed.error) {
        return { error: ts.flattenDiagnosticMessageText(parsed.error.messageText, ' ') };
    }

    return { config: parsed.config as Record<string, unknown> };
}

/** The `extends` field as a list; a single specifier — or none — normalizes to a one-element array. */
function extendsSpecifiers(config: Record<string, unknown> | undefined): unknown[] {
    const value = config?.extends;

    return Array.isArray(value) ? value : [value];
}

/**
 * Resolves a local `extends` specifier to an absolute path, applying tsconfig's
 * implicit `.json` extension. Returns null for bare package specifiers — a
 * preset reached through node_modules is not this tool's contract.
 */
function resolveLocalExtends(fromConfigPath: string, specifier: unknown): string | null {
    if (typeof specifier !== 'string' || !specifier.startsWith('.')) {
        return null;
    }

    const resolved = path.resolve(path.dirname(fromConfigPath), specifier);

    if (!fs.existsSync(resolved) && fs.existsSync(`${resolved}.json`)) {
        return `${resolved}.json`;
    }

    return resolved;
}

export function analyzeTsConfigStatically(tsconfigPath: string): StaticConfigAnalysis {
    const root = parseTsconfig(tsconfigPath);

    if (root.error || !root.config) {
        return { reachesPreset: false, declaresFiles: false, declaresPaths: false, parseError: root.error };
    }

    const compilerOptions = root.config.compilerOptions as { paths?: unknown } | undefined;
    const analysis: StaticConfigAnalysis = {
        reachesPreset: false,
        declaresFiles: root.config.files !== undefined,
        declaresPaths: compilerOptions?.paths !== undefined,
    };

    const visited = new Set<string>();
    let frontier = [{ configPath: tsconfigPath, config: root.config }];

    for (let depth = 0; depth < MAX_EXTENDS_DEPTH && frontier.length > 0; depth += 1) {
        const next: typeof frontier = [];

        for (const { configPath, config } of frontier) {
            for (const specifier of extendsSpecifiers(config)) {
                const resolved = resolveLocalExtends(configPath, specifier);

                if (resolved === null || visited.has(resolved)) {
                    continue;
                }

                visited.add(resolved);

                if (isPresetPath(resolved)) {
                    analysis.reachesPreset = true;

                    continue;
                }

                const parent = parseTsconfig(resolved);

                if (parent.config) {
                    next.push({ configPath: resolved, config: parent.config });
                }
            }
        }

        frontier = next;
    }

    return analysis;
}

export function analyzeEslintConfigStatically(eslintConfigPath: string): { importsFactory: boolean } {
    let text: string;

    try {
        text = fs.readFileSync(eslintConfigPath, 'utf8');
    } catch {
        return { importsFactory: false };
    }

    // Text-scan for the bridge or factory import. Indirect composition (via a
    // second local file) is a false negative here — the live probe corrects it
    // on the next check run.
    return {
        importsFactory: text.includes(`${SHIM_DIR_NAME}/eslint.mjs`) || text.includes('extension-tooling/eslint.mjs'),
    };
}

/** The one sentence rendered under `why:` for a TypeScript verdict. */
export function detailForTsReason(reason: ModeReason, analysis?: StaticConfigAnalysis): string {
    const aliasesNote = analysis?.declaresPaths
        ? ' Own path aliases? Declare them in tsconfig.aliases.json next to the config (see --explain).'
        : '';

    switch (reason) {
        case 'config-error':
            return analysis?.parseError ?? 'the tsconfig does not resolve.';
        case 'files-override':
            return (
                'your tsconfig declares its own "files" array, which replaces the bridge\'s ' +
                `(tsconfig extends semantics) — admin-types.d.ts never enters the program.${aliasesNote}`
            );
        case 'not-extending':
            return `the extends chain does not reach the Shopware preset or a generated .shopware-admin/ bridge.${aliasesNote}`;
        default:
            return `the resolved config does not inject extension-tooling/admin-types.d.ts.${aliasesNote}`;
    }
}

/** Static best-guess for a plugin-owned tsconfig; `verified: false` until a live probe confirms it. */
export function resolveStaticTsMode(tsconfigPath: string | null): ModeResolution {
    if (tsconfigPath === null) {
        return { mode: 'managed', verified: true };
    }

    const analysis = analyzeTsConfigStatically(tsconfigPath);

    if (analysis.parseError) {
        return { mode: 'unmanaged', reason: 'config-error', detail: analysis.parseError, verified: false };
    }

    if (!analysis.reachesPreset) {
        return {
            mode: 'unmanaged',
            reason: 'not-extending',
            detail: detailForTsReason('not-extending', analysis),
            verified: false,
        };
    }

    if (analysis.declaresFiles) {
        return {
            mode: 'unmanaged',
            reason: 'files-override',
            detail: detailForTsReason('files-override', analysis),
            verified: false,
        };
    }

    return { mode: 'bridged', verified: false };
}

export const ESLINT_NOT_COMPOSED_DETAIL =
    'the config does not compose the Shopware factory, so the preset rules never apply.';

/** Shown when a config-load failure produced no recognizable error line. */
export const ESLINT_LOAD_FAILED_DETAIL =
    'own ESLint config failed to load — run with --verbose for the underlying error (often an ESLint ' +
    'version or plugin-resolution mismatch).';

/**
 * Picks the actionable line from failed ESLint output. ESLint prefixes fatal
 * config-load errors with the generic banner `Oops! Something went wrong! :(`
 * and a version/usage preamble; surfacing that as the `why:` hides the real
 * cause behind `--verbose`. Prefer the first line that looks like a real
 * runtime error (an error class or an `ERR_*` code); fall back to a stable
 * message that names `--verbose` rather than repeating the banner.
 */
export function selectEslintErrorLine(output: string): string {
    const lines = output
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');
    const errorLine = lines.find(
        (line) =>
            /^(Error|TypeError|ReferenceError|SyntaxError|RangeError|AggregateError|EvalError|URIError)\b/.test(line) ||
            /\bERR_[A-Z0-9_]+\b/.test(line),
    );

    return errorLine ?? ESLINT_LOAD_FAILED_DETAIL;
}

/** Static best-guess for a plugin-owned ESLint config; `verified: false` until a live probe confirms it. */
export function resolveStaticEslintMode(eslintConfigPath: string | null): ModeResolution {
    if (eslintConfigPath === null) {
        return { mode: 'managed', verified: true };
    }

    if (!analyzeEslintConfigStatically(eslintConfigPath).importsFactory) {
        return {
            mode: 'unmanaged',
            reason: 'factory-not-composed',
            detail: ESLINT_NOT_COMPOSED_DETAIL,
            verified: false,
        };
    }

    return { mode: 'bridged', verified: false };
}

/** Why a live tsc probe rules a config unmanaged, once its resolved program is known not to inject the surface. */
function tsUnmanagedReason(analysis: StaticConfigAnalysis): ModeReason {
    if (analysis.declaresFiles) {
        return 'files-override';
    }

    if (!analysis.reachesPreset) {
        return 'not-extending';
    }

    return 'surface-not-injected';
}

/**
 * Live probe: a custom tsconfig composes the Shopware preset when its
 * resolved configuration reaches the shipped type surface (directly or
 * through the generated bridge). `tsc --showConfig` resolves the whole
 * extends chain.
 */
export async function probeTsMode(
    target: AdministrationTarget,
    projectRoot: string,
    administrationRoot: string,
): Promise<ModeResolution> {
    if (!target.tsconfig) {
        return target.ts;
    }

    const tscPath = path.join(administrationRoot, 'node_modules', 'typescript', 'bin', 'tsc');
    const tsconfigPath = path.resolve(projectRoot, target.tsconfig);
    const probe = await runCommand(
        process.execPath,
        [
            tscPath,
            '--showConfig',
            '--project',
            tsconfigPath,
        ],
        projectRoot,
    );

    if (probe.status !== 0) {
        const firstErrorLine =
            probe.output.split('\n').find((line) => line.trim() !== '') ?? 'the tsconfig does not resolve.';

        return {
            mode: 'unmanaged',
            reason: 'config-error',
            detail: firstErrorLine,
            probeOutput: probe.output,
            verified: true,
        };
    }

    const composes = probe.output.includes('extension-tooling/admin-types') || probe.output.includes('admin-types.d.ts');

    if (composes) {
        return { mode: 'bridged', verified: true };
    }

    const analysis = analyzeTsConfigStatically(tsconfigPath);
    const reason = tsUnmanagedReason(analysis);

    return { mode: 'unmanaged', reason, detail: detailForTsReason(reason, analysis), verified: true };
}

/**
 * Live probe: a custom ESLint config composes the Shopware preset when the
 * resolved configuration for a sample source file carries the factory's
 * runtime contract rule. (`--print-config` emits the merged config without
 * block names, so the probe checks for the rule instead.)
 */
export async function probeEslintMode(
    target: AdministrationTarget,
    projectRoot: string,
    administrationRoot: string,
    eslintBaseArguments: string[],
    sampleFile: string | null,
): Promise<ModeResolution> {
    if (!target.eslintConfig) {
        return target.eslint;
    }

    if (!sampleFile) {
        return { mode: 'bridged', verified: true };
    }

    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');
    const probe = await runCommand(
        process.execPath,
        [
            eslintPath,
            ...eslintBaseArguments,
            '--print-config',
            sampleFile,
        ],
        projectRoot,
    );

    if (probe.status !== 0) {
        return {
            mode: 'unmanaged',
            reason: 'config-error',
            detail: selectEslintErrorLine(probe.output),
            probeOutput: probe.output,
            verified: true,
        };
    }

    if (probe.output.includes('plugin-rules/no-src-imports')) {
        return { mode: 'bridged', verified: true };
    }

    return { mode: 'unmanaged', reason: 'factory-not-composed', detail: ESLINT_NOT_COMPOSED_DETAIL, verified: true };
}

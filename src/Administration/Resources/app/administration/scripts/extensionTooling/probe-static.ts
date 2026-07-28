/**
 * @sw-package framework
 *
 * Static mode resolution for extension-owned configs: synchronous, no process
 * spawns, safe for setup. Parses the config and walks its `extends` chain /
 * import specifiers to produce a fast best guess (`verified: false`) that the
 * setup report renders.
 *
 * The authority is the live probe in `./probe-live`, which resolves what the
 * tools actually see. Everything here is knowingly lossy — a bare package
 * specifier in `extends` is not followed, and the ESLint side is a text scan —
 * so a check run can and does overrule these verdicts.
 */

import fs from 'fs';
import path from 'path';
import ts from 'typescript';
import { SHIM_DIR_NAME } from './shared';
import type { ModeReason, ModeResolution } from './shared';

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
        ? ' Own path aliases? Declare them in tsconfig.aliases.json next to the config.'
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
export function tsUnmanagedReason(analysis: StaticConfigAnalysis): ModeReason {
    if (analysis.declaresFiles) {
        return 'files-override';
    }

    if (!analysis.reachesPreset) {
        return 'not-extending';
    }

    return 'surface-not-injected';
}

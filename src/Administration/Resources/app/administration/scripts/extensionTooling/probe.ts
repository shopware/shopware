/**
 * @sw-package framework
 *
 * Mode resolution for extension-owned configs. Three layers:
 *
 * 1. Static analysis — synchronous, no process spawns, safe for setup: parse
 *    the config and walk its `extends` chain / import specifiers.
 * 2. Live probes — asynchronous `tsc --showConfig` / `eslint --print-config`
 *    runs, the authority, executed by the check command.
 * 3. The probe cache — carries verified live verdicts from check runs back
 *    into subsequent setup runs (keyed by content hashes of every input the
 *    verdict depends on), so both commands render the same state.
 */

import { execFile } from 'child_process';
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { promisify } from 'util';
import ts from 'typescript';
import { SHIM_DIR_NAME, STATE_DIR, writeStateFile } from './shared';
import type { ExtensionToolingProject, ModeReason, ModeResolution } from './shared';

const execFileAsync = promisify(execFile);

export const PROCESS_TIMEOUT_MS = 10 * 60 * 1000;
const MAX_BUFFER = 100 * 1024 * 1024;

/** How deep an `extends` chain is followed before giving up. */
const MAX_EXTENDS_DEPTH = 10;

export interface CommandResult {
    status: number;
    output: string;
    durationMs: number;
    timedOut: boolean;
}

export async function runCommand(command: string, args: string[], cwd: string): Promise<CommandResult> {
    const startedAt = Date.now();

    try {
        const { stdout, stderr } = await execFileAsync(command, args, {
            cwd,
            timeout: PROCESS_TIMEOUT_MS,
            maxBuffer: MAX_BUFFER,
        });

        return {
            status: 0,
            output: `${stdout ?? ''}${stderr ? `\n${stderr}` : ''}`.trim(),
            durationMs: Date.now() - startedAt,
            timedOut: false,
        };
    } catch (error) {
        const failure = error as NodeJS.ErrnoException & {
            stdout?: string;
            stderr?: string;
            code?: number | string;
            killed?: boolean;
        };

        return {
            status: typeof failure.code === 'number' ? failure.code : 1,
            output: `${failure.stdout ?? ''}${failure.stderr ? `\n${failure.stderr}` : ''}`.trim() || failure.message,
            durationMs: Date.now() - startedAt,
            timedOut: failure.killed === true,
        };
    }
}

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
            const extendsValue = config.extends;
            const extendsList = Array.isArray(extendsValue) ? extendsValue : [extendsValue];

            for (const specifier of extendsList) {
                // Bare package specifiers are not followed — a preset reached
                // through node_modules is not this tool's contract anyway.
                if (typeof specifier !== 'string' || !specifier.startsWith('.')) {
                    continue;
                }

                let resolved = path.resolve(path.dirname(configPath), specifier);

                if (!fs.existsSync(resolved) && fs.existsSync(`${resolved}.json`)) {
                    resolved = `${resolved}.json`;
                }

                if (visited.has(resolved)) {
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
    // through the cache on the next check run.
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

    return { mode: 'custom', verified: false };
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

    return { mode: 'custom', verified: false };
}

/**
 * Live probe: a custom tsconfig composes the Shopware preset when its
 * resolved configuration reaches the shipped type surface (directly or
 * through the generated bridge). `tsc --showConfig` resolves the whole
 * extends chain.
 */
export async function probeTsMode(
    project: ExtensionToolingProject,
    projectRoot: string,
    administrationRoot: string,
): Promise<ModeResolution> {
    if (!project.tsconfig) {
        return project.ts;
    }

    const tscPath = path.join(administrationRoot, 'node_modules', 'typescript', 'bin', 'tsc');
    const tsconfigPath = path.resolve(projectRoot, project.tsconfig);
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
        return { mode: 'custom', verified: true };
    }

    const analysis = analyzeTsConfigStatically(tsconfigPath);
    const reason = analysis.declaresFiles
        ? 'files-override'
        : !analysis.reachesPreset
          ? 'not-extending'
          : 'surface-not-injected';

    return { mode: 'unmanaged', reason, detail: detailForTsReason(reason, analysis), verified: true };
}

/**
 * Live probe: a custom ESLint config composes the Shopware preset when the
 * resolved configuration for a sample source file carries the factory's
 * runtime contract rule. (`--print-config` emits the merged config without
 * block names, so the probe checks for the rule instead.)
 */
export async function probeEslintMode(
    project: ExtensionToolingProject,
    projectRoot: string,
    administrationRoot: string,
    eslintBaseArguments: string[],
    sampleFile: string | null,
): Promise<ModeResolution> {
    if (!project.eslintConfig) {
        return project.eslint;
    }

    if (!sampleFile) {
        return { mode: 'custom', verified: true };
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
        const firstErrorLine = probe.output.split('\n').find((line) => line.trim() !== '') ?? 'the config does not resolve.';

        return {
            mode: 'unmanaged',
            reason: 'config-error',
            detail: firstErrorLine,
            probeOutput: probe.output,
            verified: true,
        };
    }

    if (probe.output.includes('plugin-rules/no-src-imports')) {
        return { mode: 'custom', verified: true };
    }

    return { mode: 'unmanaged', reason: 'factory-not-composed', detail: ESLINT_NOT_COMPOSED_DETAIL, verified: true };
}

interface ProbeCacheEntry {
    key: string;
    resolution: ModeResolution;
}

export interface ProbeCacheFile {
    version: 1;
    entries: Record<string, { ts?: ProbeCacheEntry; eslint?: ProbeCacheEntry }>;
}

function probeCacheFilePath(projectRoot: string): string {
    return path.join(projectRoot, STATE_DIR, 'probe-cache.json');
}

/** Every file whose content a probe verdict depends on, per tool. */
export function probeInputFiles(
    project: ExtensionToolingProject,
    projectRoot: string,
    administrationRoot: string,
): { ts: string[]; eslint: string[] } {
    const adminFolders = project.sourcePaths.map((sourcePath) => path.dirname(path.resolve(projectRoot, sourcePath)));
    const tsFiles = [
        ...(project.tsconfig ? [path.resolve(projectRoot, project.tsconfig)] : []),
        ...adminFolders.flatMap((adminFolder) => [
            path.join(adminFolder, SHIM_DIR_NAME, 'tsconfig.json'),
            path.join(adminFolder, 'tsconfig.aliases.json'),
        ]),
        path.join(administrationRoot, 'extension-tooling', 'tsconfig.base.json'),
    ];
    const eslintFiles = [
        ...(project.eslintConfig ? [path.resolve(projectRoot, project.eslintConfig)] : []),
        ...adminFolders.map((adminFolder) => path.join(adminFolder, SHIM_DIR_NAME, 'eslint.mjs')),
        path.join(administrationRoot, 'extension-tooling', 'eslint.mjs'),
    ];

    return { ts: tsFiles, eslint: eslintFiles };
}

/** Content hash over the given files; missing files hash as empty so adding one changes the key. */
export function probeCacheKey(filePaths: string[]): string {
    const hash = crypto.createHash('sha256');

    for (const filePath of filePaths) {
        hash.update(filePath);
        hash.update(' ');

        try {
            hash.update(fs.readFileSync(filePath));
        } catch {
            // Missing input hashes as empty content.
        }

        hash.update(' ');
    }

    return hash.digest('hex');
}

export function readProbeCache(projectRoot: string): ProbeCacheFile | null {
    try {
        const parsed = JSON.parse(fs.readFileSync(probeCacheFilePath(projectRoot), 'utf8')) as ProbeCacheFile;

        return parsed && parsed.version === 1 && typeof parsed.entries === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

/**
 * Persists the cache. Deliberately not recorded as a managed write: a cache
 * refresh from a check run must not make the next `setup --check` report
 * drift.
 */
export function writeProbeCache(projectRoot: string, cache: ProbeCacheFile): void {
    writeStateFile(probeCacheFilePath(projectRoot), `${JSON.stringify(cache, null, 4)}\n`);
}

/** A cached resolution without the potentially large raw probe output. */
export function toCacheableResolution(resolution: ModeResolution): ModeResolution {
    const { probeOutput, ...rest } = resolution;

    void probeOutput;

    return rest;
}

/**
 * Adopts cached verified verdicts when the content hashes still match;
 * otherwise the given (static, unverified) resolutions stand.
 */
export function resolveModesFromCache(
    project: ExtensionToolingProject,
    cache: ProbeCacheFile | null,
    projectRoot: string,
    administrationRoot: string,
): { ts: ModeResolution; eslint: ModeResolution } {
    if (!cache) {
        return { ts: project.ts, eslint: project.eslint };
    }

    const entry = cache.entries[project.name];

    if (!entry) {
        return { ts: project.ts, eslint: project.eslint };
    }

    const inputs = probeInputFiles(project, projectRoot, administrationRoot);

    return {
        ts: project.tsconfig && entry.ts && entry.ts.key === probeCacheKey(inputs.ts) ? entry.ts.resolution : project.ts,
        eslint:
            project.eslintConfig && entry.eslint && entry.eslint.key === probeCacheKey(inputs.eslint)
                ? entry.eslint.resolution
                : project.eslint,
    };
}

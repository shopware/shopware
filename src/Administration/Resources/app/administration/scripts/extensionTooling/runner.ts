/**
 * @sw-package framework
 *
 * The runner: synthesises one ephemeral TypeScript program and one ephemeral
 * ESLint flat config per Administration source root below
 * `var/admin-extension-tooling/<slug>/`, then spawns the Administration's own
 * `tsc` and `eslint` against them.
 *
 * Because the runner owns the configs it executes, no extension can break
 * inheritance: the `files` / `baseUrl` / `types` traps of a plugin-authored
 * `tsconfig.json` cannot exist on this path by construction. And because
 * nothing is written into the extension, an extension needs to commit nothing
 * at all to be checked.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { pathToFileURL } from 'url';
import { PRESET_DIR, TOOLING_DIR, findNearestTsconfig, isWithin, readJsoncFile, relativePosix, toPosix } from './shared';
import type { AdminRoot, CheckReport, Finding, RootReport, ToolRun } from './shared';

export interface SpawnResult {
    status: number | null;
    stdout: string;
    stderr: string;
}

export type SpawnTool = (command: string, args: string[], options: { cwd: string }) => SpawnResult;

export interface RunnerOptions {
    projectRoot: string;
    administrationRoot: string;
    roots: AdminRoot[];
    types: boolean;
    lint: boolean;
    fix: boolean;
    /**
     * Seam for tests. Production code must never pass a different `cwd` than the
     * project root — see `spawnWithProjectRoot`.
     */
    spawn?: SpawnTool;
}

const TS_DIAGNOSTIC =
    /^(?<file>.+)\((?<line>\d+),(?<column>\d+)\): (?<severity>error|warning) (?<code>TS\d+): (?<message>.*)$/;
const TS_GLOBAL_DIAGNOSTIC = /^(?<severity>error|warning) (?<code>TS\d+): (?<message>.*)$/;

const ENTITY_SCHEMA_STUB = `/* eslint-disable */
/* Generated stub — replaced by "composer admin:generate-entity-schema-types". */
declare namespace EntitySchema {
    interface Entities {}
}
`;

/**
 * ESLint derives the base path of an explicit `--config` from the process cwd,
 * not from the config's location (`ConfigLoader.locateConfigFileToUse`). Spawned
 * from anywhere else, every target file counts as "outside the base path", so
 * ESLint exits 0 having linted nothing — green without having checked anything.
 * Every spawn therefore goes through this one function.
 */
function spawnWithProjectRoot(spawnTool: SpawnTool, command: string, args: string[], projectRoot: string): SpawnResult {
    return spawnTool(command, args, { cwd: projectRoot });
}

const defaultSpawn: SpawnTool = (command, args, options) => {
    const result = spawnSync(command, args, {
        cwd: options.cwd,
        encoding: 'utf8',
        // `tsc --listFiles` on a real extension program prints thousands of lines.
        maxBuffer: 64 * 1024 * 1024,
    });

    if (result.error) {
        return { status: null, stdout: '', stderr: result.error.message };
    }

    return { status: result.status, stdout: result.stdout ?? '', stderr: result.stderr ?? '' };
};

/**
 * `admin-types.d.ts` imports the generated entity schema. It is git-ignored and
 * so cannot ship as a committed placeholder; when it has not been generated yet
 * a stub keeps `EntitySchema.Entities` empty, which makes missing schema types
 * fail loudly instead of degrading to `any`.
 *
 * This is the single write below the Administration's own package, and the only
 * write outside `var/` the runner performs.
 */
export function ensureEntitySchema(administrationRoot: string): void {
    const schemaPath = path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts');

    if (!fs.existsSync(schemaPath)) {
        fs.mkdirSync(path.dirname(schemaPath), { recursive: true });
        fs.writeFileSync(schemaPath, ENTITY_SCHEMA_STUB, 'utf8');
    }
}

/**
 * The `paths` of a tsconfig, resolved to absolute values against its own
 * `baseUrl`.
 *
 * Absolute values rather than a re-anchored `baseUrl`, because the generated
 * program lives in `var/` and a `baseUrl` there would also become a second
 * bare-specifier resolution root for everything else.
 */
function absolutePathsOf(tsconfigPath: string): Record<string, string[]> {
    let config: { compilerOptions?: { baseUrl?: unknown; paths?: unknown } };

    try {
        config = readJsoncFile(tsconfigPath);
    } catch {
        return {};
    }

    const declaredPaths = config.compilerOptions?.paths;

    if (typeof declaredPaths !== 'object' || declaredPaths === null || Array.isArray(declaredPaths)) {
        return {};
    }

    const baseUrl = typeof config.compilerOptions?.baseUrl === 'string' ? config.compilerOptions.baseUrl : '.';
    const anchor = path.resolve(path.dirname(tsconfigPath), baseUrl);
    const resolved: Record<string, string[]> = {};

    for (const [
        specifier,
        targets,
    ] of Object.entries(declaredPaths as Record<string, unknown>)) {
        if (!Array.isArray(targets)) {
            continue;
        }

        resolved[specifier] = targets
            .filter((target): target is string => typeof target === 'string')
            .map((target) => toPosix(path.resolve(anchor, target)));
    }

    return resolved;
}

/** The `paths` an extension declares for itself, resolved to absolute values. */
export function collectExtensionPaths(root: AdminRoot): Record<string, string[]> {
    const tsconfigPath = findNearestTsconfig(root.adminFolder, root.extensionRoot);

    return tsconfigPath === null ? {} : absolutePathsOf(tsconfigPath);
}

/**
 * How the Administration resolves its own modules, translated for a program that
 * lives somewhere else.
 *
 * This is not optional convenience. `admin-types.d.ts` pulls the Administration's
 * sources into every extension program, and those sources import each other as
 * `src/…`, which the Administration's own `baseUrl` resolves. Without an
 * equivalent, `global.types.ts` cannot resolve `ShopwareClass`, so the global
 * `Shopware` is declared as the *error type* — and an error type absorbs every
 * property access silently. The type check then passes while checking nothing.
 * Measured on a real extension: 70 unresolved modules and a `Shopware` that
 * accepts anything, versus 0 unresolved modules with these mappings.
 *
 * The wildcard reproduces the Administration's `baseUrl`, and its second
 * substitution its `node_modules`, so an extension importing a host package —
 * `vue` being the common case — resolves against the installed version too. The
 * explicit entries come from the Administration's own tsconfig, so the host's
 * mappings cannot drift away from a list maintained here by hand.
 *
 * The preset itself still declares no `paths` (a plugin-authored config would
 * replace them wholesale on inheritance); these live only in the program the
 * runner writes and owns.
 */
export function collectHostPaths(administrationRoot: string): Record<string, string[]> {
    const administrationPosix = toPosix(administrationRoot);

    return {
        ...absolutePathsOf(path.join(administrationRoot, 'tsconfig.json')),
        '*': [
            `${administrationPosix}/*`,
            `${administrationPosix}/node_modules/*`,
        ],
    };
}

export interface GeneratedProgram {
    directory: string;
    tsconfigPath: string;
    eslintConfigPath: string;
}

export function writeProgram(root: AdminRoot, projectRoot: string, administrationRoot: string): GeneratedProgram {
    const directory = path.join(projectRoot, 'var', TOOLING_DIR, root.slug);

    fs.mkdirSync(directory, { recursive: true });

    const presetDir = path.join(administrationRoot, PRESET_DIR);
    const tsconfigPath = path.join(directory, 'tsconfig.json');
    const eslintConfigPath = path.join(directory, 'eslint.config.mjs');
    // The extension's own mappings win: it knows its aliases, and a mapping it
    // declares must not be shadowed by the host wildcard.
    const paths = {
        ...collectHostPaths(administrationRoot),
        ...collectExtensionPaths(root),
    };

    const program = {
        extends: toPosix(path.join(presetDir, 'tsconfig.base.json')),
        compilerOptions: { paths },
        files: [toPosix(path.join(presetDir, 'admin-types.d.ts'))],
        // An extensionless glob matches only the extensions the host understands:
        // `tsc` therefore sees the TS and JS sources, while ESLint — which passes
        // `extraFileExtensions: ['.vue']` into the same program — also sees the SFCs.
        include: [`${toPosix(root.sourcePath)}/**/*`],
    };

    fs.writeFileSync(tsconfigPath, `${JSON.stringify(program, null, 4)}\n`, 'utf8');

    const presetUrl = pathToFileURL(path.join(presetDir, 'eslint.mjs')).href;
    const eslintConfig = `// Generated by "administration:extension:check" for ${root.bundleName}. Do not edit.
import { shopwareAdminExtension } from ${JSON.stringify(presetUrl)};

export default shopwareAdminExtension({
    tsconfigRootDir: ${JSON.stringify(toPosix(directory))},
    project: [${JSON.stringify(toPosix(tsconfigPath))}],
});
`;

    fs.writeFileSync(eslintConfigPath, eslintConfig, 'utf8');

    return { directory, tsconfigPath, eslintConfigPath };
}

interface ParsedRun {
    findings: Finding[];
    externalFindings: number;
    unresolvedHostModules: number;
    filesChecked: number;
}

function parseTypeCheckOutput(output: string, projectRoot: string, sourcePath: string): ParsedRun {
    const findings: Finding[] = [];
    const files = new Set<string>();
    let externalFindings = 0;
    let unresolvedHostModules = 0;

    for (const line of output.split('\n')) {
        const trimmed = line.trimEnd();

        if (trimmed === '') {
            continue;
        }

        const diagnostic = TS_DIAGNOSTIC.exec(trimmed);

        if (diagnostic?.groups) {
            const groups = diagnostic.groups;
            const file = path.resolve(projectRoot, groups.file);

            // The Administration's own sources are part of every extension program.
            // Only what the extension owns is a finding — but an unresolved module
            // in the host is a broken type surface, not a footnote.
            if (!isWithin(file, sourcePath)) {
                externalFindings += 1;

                if (groups.code === 'TS2307') {
                    unresolvedHostModules += 1;
                }

                continue;
            }

            findings.push({
                file: relativePosix(projectRoot, file),
                line: Number(groups.line),
                column: Number(groups.column),
                severity: groups.severity === 'warning' ? 'warning' : 'error',
                rule: groups.code,
                message: groups.message,
            });

            continue;
        }

        const globalDiagnostic = TS_GLOBAL_DIAGNOSTIC.exec(trimmed);

        if (globalDiagnostic?.groups) {
            const groups = globalDiagnostic.groups;

            findings.push({
                file: null,
                line: null,
                column: null,
                severity: groups.severity === 'warning' ? 'warning' : 'error',
                rule: groups.code,
                message: groups.message,
            });

            continue;
        }

        // Everything else is either a `--listFiles` entry or the indented related
        // information of a diagnostic; only paths inside the checked root count.
        const candidate = path.resolve(projectRoot, trimmed);

        if (isWithin(candidate, sourcePath)) {
            files.add(candidate);
        }
    }

    return { findings, externalFindings, unresolvedHostModules, filesChecked: files.size };
}

interface EslintMessage {
    ruleId?: string | null;
    severity?: number;
    message?: string;
    line?: number;
    column?: number;
    fatal?: boolean;
}

interface EslintResult {
    filePath?: string;
    messages?: EslintMessage[];
}

function parseLintOutput(output: string, projectRoot: string): ParsedRun {
    const results = JSON.parse(output) as EslintResult[];

    if (!Array.isArray(results)) {
        throw new Error('ESLint did not report a JSON array.');
    }

    const findings: Finding[] = [];

    for (const result of results) {
        for (const message of result.messages ?? []) {
            findings.push({
                file: relativePosix(projectRoot, path.resolve(projectRoot, result.filePath ?? '')),
                line: message.line ?? null,
                column: message.column ?? null,
                severity: message.severity === 1 ? 'warning' : 'error',
                rule: message.ruleId ?? (message.fatal === true ? 'parse-error' : null),
                message: message.message ?? '',
            });
        }
    }

    // ESLint only ever reports on the files it was asked to lint, so nothing can
    // come from outside the checked root here.
    return { findings, externalFindings: 0, unresolvedHostModules: 0, filesChecked: results.length };
}

function typeCheck(root: AdminRoot, program: GeneratedProgram, options: RunnerOptions, spawnTool: SpawnTool): ToolRun {
    const binary = path.join(options.administrationRoot, 'node_modules', '.bin', 'tsc');
    const result = spawnWithProjectRoot(
        spawnTool,
        binary,
        [
            '--project',
            program.tsconfigPath,
            '--pretty',
            'false',
            '--listFiles',
        ],
        options.projectRoot,
    );
    const parsed = parseTypeCheckOutput(result.stdout, options.projectRoot, root.sourcePath);
    const errors: string[] = [];

    if (result.status === null) {
        errors.push(`Could not run ${binary}: ${result.stderr.trim()}`);
    } else if (result.stderr.trim() !== '') {
        errors.push(result.stderr.trim());
    }

    if (parsed.unresolvedHostModules > 0) {
        errors.push(
            `The Administration type surface did not resolve: ${parsed.unresolvedHostModules} unresolved modules in the ` +
                'host sources. The global Shopware object is an error type in this program, which silently accepts ' +
                'anything, so the type results are not meaningful.',
        );
    }

    return {
        tool: 'types',
        filesChecked: parsed.filesChecked,
        findings: parsed.findings,
        externalFindings: parsed.externalFindings,
        unresolvedHostModules: parsed.unresolvedHostModules,
        errors,
    };
}

function lint(root: AdminRoot, program: GeneratedProgram, options: RunnerOptions, spawnTool: SpawnTool): ToolRun {
    const binary = path.join(options.administrationRoot, 'node_modules', '.bin', 'eslint');
    const args = [
        '--config',
        program.eslintConfigPath,
        '--format',
        'json',
        // Ignored files must not be reported as checked: that is exactly how a
        // wrong cwd would masquerade as a clean run.
        '--no-warn-ignored',
        '--no-error-on-unmatched-pattern',
    ];

    if (options.fix) {
        args.push('--fix');
    }

    args.push(relativePosix(options.projectRoot, root.sourcePath));

    const result = spawnWithProjectRoot(spawnTool, binary, args, options.projectRoot);
    const errors: string[] = [];

    if (result.status === null) {
        return {
            tool: 'lint',
            filesChecked: 0,
            findings: [],
            externalFindings: 0,
            unresolvedHostModules: 0,
            errors: [`Could not run ${binary}: ${result.stderr.trim()}`],
        };
    }

    try {
        const parsed = parseLintOutput(result.stdout, options.projectRoot);

        if (result.status !== 0 && result.status !== 1) {
            errors.push(`ESLint exited with ${result.status}: ${result.stderr.trim()}`);
        }

        return {
            tool: 'lint',
            filesChecked: parsed.filesChecked,
            findings: parsed.findings,
            externalFindings: parsed.externalFindings,
            unresolvedHostModules: 0,
            errors,
        };
    } catch (error) {
        errors.push(`Could not read the ESLint report: ${(error as Error).message}`);

        if (result.stderr.trim() !== '') {
            errors.push(result.stderr.trim());
        }

        return { tool: 'lint', filesChecked: 0, findings: [], externalFindings: 0, unresolvedHostModules: 0, errors };
    }
}

/**
 * Removes the generated directory before writing it again: the layout is
 * replaced, never merged, so an orphaned program from an earlier run is
 * structurally impossible and nothing has to be remembered across runs.
 */
export function resetToolingDirectory(projectRoot: string): string {
    const directory = path.join(projectRoot, 'var', TOOLING_DIR);

    fs.rmSync(directory, { recursive: true, force: true });
    fs.mkdirSync(directory, { recursive: true });

    return directory;
}

export function missingBinaries(administrationRoot: string, options: { types: boolean; lint: boolean }): string[] {
    const binaries = [
        ...(options.types ? ['tsc'] : []),
        ...(options.lint ? ['eslint'] : []),
    ];

    return binaries
        .map((binary) => path.join(administrationRoot, 'node_modules', '.bin', binary))
        .filter((binary) => !fs.existsSync(binary));
}

export function runCheck(options: RunnerOptions): CheckReport {
    const spawnTool = options.spawn ?? defaultSpawn;
    const missing = missingBinaries(options.administrationRoot, options);

    if (missing.length > 0) {
        return {
            roots: [],
            errors: [
                `Administration dependencies are missing (${missing.join(', ')}).`,
                `Run "npm ci" in ${relativePosix(options.projectRoot, options.administrationRoot)}.`,
            ],
        };
    }

    ensureEntitySchema(options.administrationRoot);
    resetToolingDirectory(options.projectRoot);

    const roots: RootReport[] = options.roots.map((root) => {
        const program = writeProgram(root, options.projectRoot, options.administrationRoot);
        const runs: ToolRun[] = [];

        if (options.types) {
            runs.push(typeCheck(root, program, options, spawnTool));
        }

        if (options.lint) {
            runs.push(lint(root, program, options, spawnTool));
        }

        return { root, runs };
    });

    return { roots, errors: [] };
}

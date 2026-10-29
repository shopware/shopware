/**
 * @sw-package framework
 *
 * Upgrade runner for Administration extensions: applies the codemods that
 * prepare an extension for a Shopware major. The ESLint codemods run with an
 * isolated config that enables only their rules (extension-tooling/codemods.mjs),
 * so the extension's own lint setup never changes what gets rewritten.
 *
 * A run is a dry run unless `--write` is set. Writing refuses extensions with
 * uncommitted changes, so every run can be undone with `git checkout`.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { spawnSync } from 'child_process';
import { pathToFileURL } from 'url';
import { runMigration } from '../codemods/sfc-migration/run-sfc-migration';
import { discoverProjects } from './setup';
import { resolveCliSelection } from './select';
import {
    filterSelectedProjects,
    isUpgradable,
    matchesProjectName,
    normalizeSelection,
    pluginsConfigPath,
    resolveToolingCommands,
    toPosix,
} from './shared';
import type { ExtensionToolingProject } from './shared';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { CODEMODS, CodemodSelectionError, availableTargets, selectCodemods } from './upgrade-codemods';
import type { Codemod, EslintCodemod, SfcMigrationCodemod } from './upgrade-codemods';
import { renderCodemodList, renderUpgradeReport } from './upgrade-report';

export interface ManualSpot {
    /** Relative to the extension root (posix). */
    file: string;
    line?: number;
    column?: number;
    message: string;
}

export interface CodemodReport {
    codemod: Codemod;
    /** False for an opt-in codemod that was not requested: it is only counted, never applied. */
    enabled: boolean;
    changes: number;
    files: number;
    manual: ManualSpot[];
}

export interface ExtensionUpgrade {
    name: string;
    basePath: string;
    /** Why the extension was left out, e.g. because it is vendor-installed. */
    skipped?: string;
    codemods: CodemodReport[];
    /** Every file the run changed or would change, relative to the extension root (posix). */
    changedFiles: string[];
    warnings: string[];
    errors: string[];
}

export interface UpgradeExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    /** Defaults to the newest major this Administration ships codemods for. */
    target?: string;
    /** Extension names to upgrade; every writable extension when empty or undefined. */
    only?: string[];
    /** Names given via --only. Vendor extensions are only included when named here. */
    explicitOnly?: string[];
    /** Opt-in codemod ids requested via --with. */
    optIns?: string[];
    write?: boolean;
    /** Write even when git cannot protect the extension. */
    skipGitCheck?: boolean;
}

export interface UpgradeExtensionsResult {
    target: string;
    write: boolean;
    optIns: string[];
    extensions: ExtensionUpgrade[];
    fatalDiagnostics: string[];
    exitCode: number;
}

interface EslintMessage {
    ruleId: string | null;
    fatal?: boolean;
    line?: number;
    column?: number;
    message: string;
    fix?: unknown;
}

interface EslintFileResult {
    filePath: string;
    messages: EslintMessage[];
}

/**
 * Whether `git checkout` can undo a run: the extension has tracked files and no uncommitted changes.
 * An extension outside a work tree, or one the surrounding repository ignores, counts as untracked.
 */
function gitState(directory: string): 'clean' | 'dirty' | 'untracked' {
    const git = (...args: string[]) => spawnSync('git', args, { cwd: directory, encoding: 'utf8' });
    const tracked = git('ls-files', '--', '.');

    if (tracked.status !== 0 || tracked.stdout.trim() === '') {
        return 'untracked';
    }

    const status = git('status', '--porcelain', '--', '.');

    if (status.status !== 0) {
        return 'untracked';
    }

    return status.stdout.trim() === '' ? 'clean' : 'dirty';
}

function writeCodemodConfig(administrationRoot: string, codemods: EslintCodemod[]): string {
    const configDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-admin-codemods-'));
    const configPath = path.join(configDirectory, 'eslint.config.mjs');
    const factoryUrl = pathToFileURL(path.join(administrationRoot, 'extension-tooling', 'codemods.mjs')).href;
    const entry = ({ rule, options }: EslintCodemod) => ({ rule, options });
    const rules = {
        scriptRules: codemods.filter((codemod) => codemod.scope !== 'template').map(entry),
        templateRules: codemods.filter((codemod) => codemod.scope !== 'script').map(entry),
    };

    fs.writeFileSync(
        configPath,
        [
            `import { shopwareAdminCodemods } from ${JSON.stringify(factoryUrl)};`,
            '',
            `export default shopwareAdminCodemods(${JSON.stringify(rules)});`,
            '',
        ].join('\n'),
    );

    return configPath;
}

function runEslint(options: {
    administrationRoot: string;
    configPath: string;
    extensionRoot: string;
    sourceDirectories: string[];
    fix: boolean;
}): EslintFileResult[] | { error: string } {
    const eslintPath = path.join(options.administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

    if (!fs.existsSync(eslintPath)) {
        return { error: `ESLint is not installed in the Administration (${eslintPath}). Run "npm ci" there first.` };
    }

    const run = spawnSync(
        process.execPath,
        [
            eslintPath,
            '--config',
            options.configPath,
            '--format',
            'json',
            '--no-warn-ignored',
            // A source folder with only snippets or styles must not abort the run for the other folders.
            '--no-error-on-unmatched-pattern',
            ...(options.fix ? ['--fix'] : []),
            ...options.sourceDirectories.map((directory) => path.relative(options.extensionRoot, directory) || '.'),
        ],
        { cwd: options.extensionRoot, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 },
    );

    // ESLint exits 1 when it reports problems; only 2 (or a signal) means the run itself failed.
    if (run.status !== 0 && run.status !== 1) {
        return { error: `ESLint failed: ${(run.stderr || run.stdout || String(run.error ?? '')).trim()}` };
    }

    try {
        return JSON.parse(run.stdout) as EslintFileResult[];
    } catch {
        return { error: `ESLint returned unreadable output: ${(run.stderr || run.stdout).trim().slice(0, 500)}` };
    }
}

interface CodemodTally {
    changes: number;
    /** Relative to the extension root (posix). */
    files: Set<string>;
    manual: ManualSpot[];
}

interface CodemodRunContext {
    administrationRoot: string;
    extensionRoot: string;
    sourceDirectories: string[];
    write: boolean;
}

interface CodemodRunResult {
    tallies: Map<string, CodemodTally>;
    warnings: string[];
    errors: string[];
}

function emptyRunResult(codemods: Codemod[]): CodemodRunResult {
    return {
        tallies: new Map(codemods.map((codemod) => [codemod.id, { changes: 0, files: new Set(), manual: [] }])),
        warnings: [],
        errors: [],
    };
}

function runEslintCodemods(context: CodemodRunContext, codemods: EslintCodemod[]): CodemodRunResult {
    const result = emptyRunResult(codemods);
    const codemodByRule = new Map(codemods.map((codemod) => [codemod.rule, codemod]));
    const configPath = writeCodemodConfig(context.administrationRoot, codemods);
    const relativeFile = (filePath: string) => toPosix(path.relative(context.extensionRoot, filePath));
    const tallyOf = (message: EslintMessage) => {
        const codemod = message.ruleId ? codemodByRule.get(message.ruleId) : undefined;

        return codemod ? result.tallies.get(codemod.id) : undefined;
    };
    const lint = (fix: boolean) =>
        runEslint({
            administrationRoot: context.administrationRoot,
            configPath,
            extensionRoot: context.extensionRoot,
            sourceDirectories: context.sourceDirectories,
            fix,
        });
    const failed = (error: string): CodemodRunResult => ({
        ...emptyRunResult(codemods),
        warnings: result.warnings,
        errors: [error],
    });

    try {
        const preview = lint(false);

        if ('error' in preview) {
            return failed(preview.error);
        }

        for (const file of preview) {
            for (const message of file.messages) {
                if (message.fatal) {
                    result.warnings.push(`Could not parse ${relativeFile(file.filePath)}: ${message.message}`);
                }

                const tally = tallyOf(message);

                if (tally && message.fix) {
                    tally.changes += 1;
                    tally.files.add(relativeFile(file.filePath));
                }
            }
        }

        const hasChanges = [...result.tallies.values()].some((tally) => tally.changes > 0);
        // What is left after fixing is exactly what needs a human.
        const remaining = context.write && hasChanges ? lint(true) : preview;

        if ('error' in remaining) {
            return failed(remaining.error);
        }

        for (const file of remaining) {
            for (const message of file.messages) {
                const tally = tallyOf(message);

                if (tally && !message.fix) {
                    tally.manual.push({
                        file: relativeFile(file.filePath),
                        line: message.line,
                        column: message.column,
                        message: message.message,
                    });
                }
            }
        }

        return result;
    } finally {
        fs.rmSync(path.dirname(configPath), { recursive: true, force: true });
    }
}

async function runSfcMigration(context: CodemodRunContext, codemod: SfcMigrationCodemod): Promise<CodemodRunResult> {
    const tally: CodemodTally = { changes: 0, files: new Set(), manual: [] };
    const result: CodemodRunResult = { tallies: new Map([[codemod.id, tally]]), warnings: [], errors: [] };

    for (const sourceDirectory of context.sourceDirectories) {
        const migration = await runMigration(sourceDirectory, { write: context.write });

        for (const component of migration.reports) {
            const componentFile = toPosix(
                path.relative(context.extensionRoot, path.join(component.dir, `${component.name}.vue`)),
            );

            if (component.outcome === 'error') {
                result.errors.push(`SFC migration of ${component.name} failed: ${component.reasons.join('; ')}`);
            }

            if (component.outcome === 'full' || component.outcome === 'partial') {
                tally.changes += 1;
                tally.files.add(componentFile);
            }

            if (component.outcome === 'partial') {
                tally.manual.push({
                    file: componentFile,
                    message: 'Converted with TODO(sfc-migration) comments that need a review.',
                });
            }
        }
    }

    return result;
}

/** The SFC migration runs last, so it converts what the ESLint codemods already rewrote. */
async function runCodemods(context: CodemodRunContext, codemods: Codemod[]): Promise<CodemodRunResult[]> {
    const eslint = codemods.filter((codemod): codemod is EslintCodemod => codemod.kind === 'eslint');
    const sfc = codemods.find((codemod): codemod is SfcMigrationCodemod => codemod.kind === 'sfc-migration');
    const results: CodemodRunResult[] = [];

    if (eslint.length > 0) {
        results.push(runEslintCodemods(context, eslint));
    }

    if (sfc) {
        results.push(await runSfcMigration(context, sfc));
    }

    return results;
}

function emptyUpgrade(project: ExtensionToolingProject, skipped?: string): ExtensionUpgrade {
    return {
        name: project.name,
        basePath: project.basePath,
        skipped,
        codemods: [],
        changedFiles: [],
        warnings: [],
        errors: [],
    };
}

async function upgradeExtension(
    project: ExtensionToolingProject,
    options: { projectRoot: string; administrationRoot: string; codemods: Codemod[]; optIns: string[]; write: boolean },
): Promise<ExtensionUpgrade> {
    const upgrade = emptyUpgrade(project);
    const isEnabled = (codemod: Codemod) => !codemod.optIn || options.optIns.includes(codemod.id);
    const enabled = options.codemods.filter(isEnabled);
    // Opt-ins nobody requested only run as a dry probe that the report points out; it must never fail the run.
    const probed = options.codemods.filter((codemod) => !isEnabled(codemod));
    const context = {
        administrationRoot: options.administrationRoot,
        extensionRoot: path.join(options.projectRoot, project.basePath),
        sourceDirectories: project.targets.map((target) => path.join(options.projectRoot, target.sourcePath)),
    };
    const tallies = new Map<string, CodemodTally>();

    for (const applied of await runCodemods({ ...context, write: options.write }, enabled)) {
        applied.tallies.forEach((tally, id) => tallies.set(id, tally));
        upgrade.warnings.push(...applied.warnings);
        upgrade.errors.push(...applied.errors);
    }

    for (const probe of await runCodemods({ ...context, write: false }, probed)) {
        probe.tallies.forEach((tally, id) => tallies.set(id, { ...tally, manual: [] }));
    }

    upgrade.codemods = options.codemods.map((codemod) => {
        const tally = tallies.get(codemod.id);

        return {
            codemod,
            enabled: isEnabled(codemod),
            changes: tally?.changes ?? 0,
            files: tally?.files.size ?? 0,
            manual: tally?.manual ?? [],
        };
    });
    upgrade.changedFiles = [...new Set(enabled.flatMap((codemod) => [...(tallies.get(codemod.id)?.files ?? [])]))].sort();

    return upgrade;
}

export async function upgradeExtensions(options: UpgradeExtensionsOptions): Promise<UpgradeExtensionsResult> {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const targets = availableTargets();
    const target = options.target ?? targets[targets.length - 1];
    const optIns = options.optIns ?? [];
    const write = options.write ?? false;
    const codemods = selectCodemods(target, optIns);
    const explicitOnly = options.explicitOnly ?? [];
    const result: UpgradeExtensionsResult = { target, write, optIns, extensions: [], fatalDiagnostics: [], exitCode: 0 };
    const discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath(projectRoot));
    const selection = filterSelectedProjects(discovered, options.only ?? []);

    if (selection.fatalDiagnostic) {
        return { ...result, fatalDiagnostics: [selection.fatalDiagnostic], exitCode: 1 };
    }

    const runnable: ExtensionToolingProject[] = [];

    for (const project of selection.projects) {
        if (isUpgradable(project) || explicitOnly.some((name) => matchesProjectName(project, name))) {
            runnable.push(project);
        } else if (project.vendor) {
            result.extensions.push(emptyUpgrade(project, 'vendor-installed, name it via --only to include it'));
        }
    }

    if (write && !options.skipGitCheck) {
        const blocked = runnable.flatMap((project) => {
            const state = gitState(path.join(projectRoot, project.basePath));

            if (state === 'dirty') {
                return [
                    `${project.basePath} has uncommitted changes. ` +
                        'Commit or stash them first, so you can undo the codemods with git checkout.',
                ];
            }

            if (state === 'untracked') {
                return [
                    `${project.basePath} is not tracked by git, so git checkout could not undo the codemods. ` +
                        'Commit it to git first, or pass --skip-git-check.',
                ];
            }

            return [];
        });

        if (blocked.length > 0) {
            return { ...result, fatalDiagnostics: [...blocked, 'Nothing was written.'], exitCode: 1 };
        }
    }

    for (const project of runnable) {
        result.extensions.push(
            await upgradeExtension(project, { projectRoot, administrationRoot, codemods, optIns, write }),
        );
    }

    result.exitCode = result.extensions.some((extension) => extension.errors.length > 0) ? 1 : 0;

    return result;
}

const UPGRADE_COMMAND: CommandSpec = {
    command: 'admin:upgrade-extensions',
    description:
        'Apply the codemods that prepare Administration extensions for a Shopware major. ' +
        'Dry run unless --write is set.',
    flags: [
        {
            name: '--only',
            value: 'required',
            valueName: '<name[,name]>',
            description: 'Upgrade only the named extensions (skips the interactive picker).',
        },
        { name: '--all', description: 'Upgrade every writable extension (skips the interactive picker).' },
        {
            name: '--target',
            value: 'required',
            valueName: '<major>',
            description: `Shopware major to prepare for, e.g. ${availableTargets().join(' or ')} (defaults to the newest).`,
        },
        {
            name: '--with',
            value: 'required',
            valueName: '<codemod[,codemod]>',
            description: `Also run opt-in codemods: ${CODEMODS.filter((codemod) => codemod.optIn)
                .map((codemod) => codemod.id)
                .join(', ')}.`,
        },
        {
            name: '--write',
            description: 'Write the changes. Refuses extensions that are not committed to git.',
        },
        {
            name: '--skip-git-check',
            description: 'Write even when git cannot undo the changes (untracked or uncommitted extensions).',
        },
        { name: '--list', description: 'List the available codemods per target and exit.' },
        {
            name: '--project-root',
            value: 'required',
            valueName: '<path>',
            description: 'Shop root (defaults to the PROJECT_ROOT env).',
        },
        {
            name: '--administration-root',
            value: 'required',
            valueName: '<path>',
            description: 'Administration app root (defaults to the installed one running this script).',
        },
    ],
};

/** Runs the upgrade command; returns the process exit code (0 ok, 1 blocked/error, 2 usage error). */
export async function runUpgradeCli(argv: string[]): Promise<number> {
    let parsed;

    try {
        parsed = parseCli(argv, UPGRADE_COMMAND);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(UPGRADE_COMMAND)}`);

            return 2;
        }

        throw error;
    }

    if (parsed.help) {
        console.log(renderHelp(UPGRADE_COMMAND));

        return 0;
    }

    if (parsed.flags.has('--list')) {
        console.log(renderCodemodList(CODEMODS));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        console.error(`PROJECT_ROOT or --project-root is required.\n\n${renderHelp(UPGRADE_COMMAND)}`);

        return 2;
    }

    const selection = await resolveCliSelection({
        only: parsed.values['--only'],
        all: parsed.flags.has('--all'),
        candidates: () => {
            const resolvedRoot = path.resolve(projectRoot);

            return discoverProjects(resolvedRoot, administrationRoot, pluginsConfigPath(resolvedRoot)).filter(isUpgradable);
        },
        messages: {
            title: 'Select extensions to upgrade:',
            empty: 'No writable Administration extensions discovered.',
            fallback: 'Not an interactive terminal and no selection given — upgrading all writable extensions.',
        },
    });

    if (!selection) {
        return 0;
    }

    let result;

    try {
        result = await upgradeExtensions({
            projectRoot,
            administrationRoot,
            target: parsed.values['--target'],
            only: selection.only,
            explicitOnly: selection.explicitOnly,
            optIns: normalizeSelection(parsed.values['--with']),
            write: parsed.flags.has('--write'),
            skipGitCheck: parsed.flags.has('--skip-git-check'),
        });
    } catch (error) {
        if (error instanceof CodemodSelectionError) {
            console.error(error.message);

            return 2;
        }

        throw error;
    }

    console.log(renderUpgradeReport(result, resolveToolingCommands(path.resolve(projectRoot), administrationRoot)));

    return result.exitCode;
}

if (require.main === module) {
    runUpgradeCli(process.argv.slice(2)).then(
        (exitCode) => {
            process.exitCode = exitCode;
        },
        (error: unknown) => {
            console.error(error instanceof Error ? error.message : error);
            process.exitCode = 1;
        },
    );
}

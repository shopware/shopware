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
import { promptSelection } from './select';
import {
    filterSelectedProjects,
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
import type { Codemod, EslintCodemod } from './upgrade-codemods';
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

/** Bundles of the platform itself; custom/static-plugins and other project folders are regular extensions. */
function isPlatformBundle(project: ExtensionToolingProject): boolean {
    return !project.vendor && project.basePath.startsWith('src/');
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

function applyEslintCodemods(options: {
    administrationRoot: string;
    extensionRoot: string;
    sourceDirectories: string[];
    codemods: EslintCodemod[];
    reports: Map<string, CodemodReport>;
    write: boolean;
    upgrade: ExtensionUpgrade;
    changedFiles: Set<string>;
}): void {
    const { codemods, reports, upgrade } = options;
    const codemodByRule = new Map(codemods.map((codemod) => [codemod.rule, codemod]));
    const configPath = writeCodemodConfig(options.administrationRoot, codemods);
    const relativeFile = (filePath: string) => toPosix(path.relative(options.extensionRoot, filePath));
    const lint = (fix: boolean) =>
        runEslint({
            administrationRoot: options.administrationRoot,
            configPath,
            extensionRoot: options.extensionRoot,
            sourceDirectories: options.sourceDirectories,
            fix,
        });
    const collectManual = (results: EslintFileResult[]) => {
        for (const result of results) {
            for (const message of result.messages) {
                const codemod = message.ruleId ? codemodByRule.get(message.ruleId) : undefined;

                if (codemod && !message.fix) {
                    reports.get(codemod.id)?.manual.push({
                        file: relativeFile(result.filePath),
                        line: message.line,
                        column: message.column,
                        message: message.message,
                    });
                }
            }
        }
    };

    try {
        const preview = lint(false);

        if ('error' in preview) {
            upgrade.errors.push(preview.error);

            return;
        }

        const fixableFiles = new Set<string>();

        for (const result of preview) {
            const fixedRules = new Set<string>();

            for (const message of result.messages) {
                if (message.fatal) {
                    upgrade.warnings.push(`Could not parse ${relativeFile(result.filePath)}: ${message.message}`);

                    continue;
                }

                const codemod = message.ruleId ? codemodByRule.get(message.ruleId) : undefined;

                if (codemod && message.fix) {
                    const report = reports.get(codemod.id);

                    if (report) {
                        report.changes += 1;
                        report.files += fixedRules.has(codemod.id) ? 0 : 1;
                    }

                    fixedRules.add(codemod.id);
                    fixableFiles.add(relativeFile(result.filePath));
                }
            }
        }

        const hasChanges = codemods.some((codemod) => (reports.get(codemod.id)?.changes ?? 0) > 0);

        if (!options.write || !hasChanges) {
            collectManual(preview);
            fixableFiles.forEach((file) => options.changedFiles.add(file));

            return;
        }

        // What is left after fixing is exactly what needs a human.
        const remaining = lint(true);

        if ('error' in remaining) {
            upgrade.errors.push(remaining.error);

            for (const codemod of codemods) {
                const report = reports.get(codemod.id);

                if (report) {
                    report.changes = 0;
                    report.files = 0;
                }
            }

            return;
        }

        collectManual(remaining);
        fixableFiles.forEach((file) => options.changedFiles.add(file));
    } finally {
        fs.rmSync(path.dirname(configPath), { recursive: true, force: true });
    }
}

async function applySfcMigration(options: {
    extensionRoot: string;
    sourceDirectories: string[];
    report: CodemodReport;
    write: boolean;
    upgrade: ExtensionUpgrade;
    changedFiles: Set<string>;
}): Promise<void> {
    const { report, upgrade } = options;

    for (const sourceDirectory of options.sourceDirectories) {
        const result = await runMigration(sourceDirectory, { write: options.write && report.enabled });

        for (const component of result.reports) {
            const componentFile = toPosix(
                path.relative(options.extensionRoot, path.join(component.dir, `${component.name}.vue`)),
            );

            if (component.outcome === 'full' || component.outcome === 'partial') {
                report.changes += 1;
                report.files += 1;

                if (report.enabled) {
                    options.changedFiles.add(componentFile);
                }
            }

            if (component.outcome === 'partial' && report.enabled) {
                report.manual.push({
                    file: componentFile,
                    message: 'Converted with TODO(sfc-migration) comments that need a review.',
                });
            }

            // A dry probe for an opt-in nobody requested must never fail the run.
            if (component.outcome === 'error' && report.enabled) {
                upgrade.errors.push(`SFC migration of ${component.name} failed: ${component.reasons.join('; ')}`);
            }
        }
    }
}

async function upgradeExtension(
    project: ExtensionToolingProject,
    options: { projectRoot: string; administrationRoot: string; codemods: Codemod[]; optIns: string[]; write: boolean },
): Promise<ExtensionUpgrade> {
    const extensionRoot = path.join(options.projectRoot, project.basePath);
    const sourceDirectories = project.targets.map((target) => path.join(options.projectRoot, target.sourcePath));
    const reports = new Map<string, CodemodReport>(
        options.codemods.map((codemod) => [
            codemod.id,
            {
                codemod,
                enabled: !codemod.optIn || options.optIns.includes(codemod.id),
                changes: 0,
                files: 0,
                manual: [],
            },
        ]),
    );
    const upgrade: ExtensionUpgrade = {
        name: project.name,
        basePath: project.basePath,
        codemods: [...reports.values()],
        changedFiles: [],
        warnings: [],
        errors: [],
    };
    const changedFiles = new Set<string>();
    const eslintCodemods = options.codemods.filter(
        (codemod): codemod is EslintCodemod => codemod.kind === 'eslint' && reports.get(codemod.id)?.enabled === true,
    );

    if (eslintCodemods.length > 0) {
        applyEslintCodemods({
            administrationRoot: options.administrationRoot,
            extensionRoot,
            sourceDirectories,
            codemods: eslintCodemods,
            reports,
            write: options.write,
            upgrade,
            changedFiles,
        });
    }

    const sfcReport = upgrade.codemods.find((report) => report.codemod.kind === 'sfc-migration');

    if (sfcReport) {
        await applySfcMigration({
            extensionRoot,
            sourceDirectories,
            report: sfcReport,
            write: options.write,
            upgrade,
            changedFiles,
        });
    }

    upgrade.changedFiles = [...changedFiles].sort();

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
        const named = explicitOnly.some((name) => matchesProjectName(project, name));

        if (named || (!project.vendor && !isPlatformBundle(project))) {
            runnable.push(project);
        } else if (project.vendor) {
            result.extensions.push({
                name: project.name,
                basePath: project.basePath,
                skipped: 'vendor-installed, name it via --only to include it',
                codemods: [],
                changedFiles: [],
                warnings: [],
                errors: [],
            });
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

    const only = parsed.values['--only'];
    const explicitOnly = only === undefined ? [] : normalizeSelection(only);
    let selection: string[] | undefined;

    if (only !== undefined) {
        selection = explicitOnly;
    } else if (!parsed.flags.has('--all') && process.stdin.isTTY && process.stdout.isTTY) {
        const resolvedRoot = path.resolve(projectRoot);
        const projects = discoverProjects(resolvedRoot, administrationRoot, pluginsConfigPath(resolvedRoot)).filter(
            (project) => !project.vendor && !isPlatformBundle(project),
        );

        if (projects.length === 0) {
            console.log('No writable Administration extensions discovered.');

            return 0;
        }

        const choice = await promptSelection(projects);

        if (choice === 'cancel') {
            console.log('Nothing selected.');

            return 0;
        }

        selection = choice === 'all' ? undefined : choice.names;
    }

    let result;

    try {
        result = await upgradeExtensions({
            projectRoot,
            administrationRoot,
            target: parsed.values['--target'],
            only: selection,
            explicitOnly,
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

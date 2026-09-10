/**
 * @sw-package framework
 *
 * Check runner for Administration extensions: runs setup first (fresh
 * state), then per extension vue-tsc and ESLint with the Administration's
 * pinned toolchain. Tool output is passed through natively under a
 * per-extension header — no parsing layer rewrites or drops diagnostics;
 * parsing only informs exit codes and summary counts.
 *
 * Severity policy: findings in writable extensions (custom/plugins and
 * in-repo platform bundles) fail the check; findings in vendor/ extensions
 * are reported non-fatally unless --strict-vendor is set. Custom configs
 * that do not compose the Shopware preset are visibly skipped as
 * "unmanaged" — never silently green.
 *
 * This module owns the orchestration and the CLI entrypoint; the reusable
 * machinery lives in the sibling check-* modules, which callers import
 * directly.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { discoverProjects, setupExtensionTooling } from './setup';
import type { SetupExtensionToolingResult } from './setup';
import { renderCheckReport } from './report';
import { promptSelection } from './select';
import { pluginsConfigPath, readEslintMajorVersion, relativePosix, resolveToolingCommands } from './shared';
import type { ExtensionToolingProject, SetupWarning, ToolingCommands } from './shared';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { createLimiter, runPool } from './check-pipeline';
import { checkProject, computeExitCode, probeExtensionModes, recordProjectBaseline } from './check-run';
import type { CheckExtensionsOptions, CheckExtensionsResult } from './check-types';

/** Normalizes the selection (single value, comma list, or array) to trimmed, non-empty names. */
export function normalizeSelection(only: string | string[] | undefined): string[] {
    if (!only) {
        return [];
    }

    return (Array.isArray(only) ? only : only.split(',')).map((value) => value.trim()).filter((value) => value !== '');
}

/** The fatal, run-blocking diagnostics that setup itself surfaces (missing schema, user-owned root configs). */
function collectSetupDiagnostics(setupResult: SetupExtensionToolingResult, commands: ToolingCommands): string[] {
    const diagnostics: string[] = [];

    if (!setupResult.manifest.entitySchemaAvailable) {
        diagnostics.push(
            'Entity schema types are missing — entity names cannot be type-checked against this installation, ' +
                `so TypeScript checks were not run. Fix: ${commands.generateSchema}`,
        );
    }

    if (setupResult.manifest.rootConfigs.tsconfig === 'conflict') {
        diagnostics.push(
            'The root tsconfig.json is user-owned and does not come from this tool — the IDE view and this check ' +
                `may diverge. Fix: integrate the printed references or remove the file and re-run ${commands.setup}`,
        );
    }

    if (setupResult.manifest.rootConfigs.eslintConfig === 'conflict') {
        diagnostics.push(
            'The root eslint.config.mjs is user-owned and does not come from this tool. Fix: compose the shared ' +
                `factory as printed, or remove the file and re-run ${commands.setup}`,
        );
    }

    return diagnostics;
}

/** Whether a --only entry names this project, by extension name or by one of its bundle technical names. */
function matches(project: ExtensionToolingProject, name: string): boolean {
    return project.name === name || project.technicalNames.includes(name);
}

/**
 * Setup runs over every installed extension, so its per-extension warnings
 * would report extensions this run does not check. Project-global warnings
 * (a missing entity schema, a missing host module) carry no extension and
 * always stay — they affect the selected run too.
 */
function selectedSetupWarnings(warnings: SetupWarning[], projects: ExtensionToolingProject[], selected: string[]): string[] {
    return warnings
        .filter(
            (warning) =>
                warning.extension === undefined ||
                selected.length === 0 ||
                projects.some(
                    (project) => project.name === warning.extension && selected.some((name) => matches(project, name)),
                ),
        )
        .map((warning) => warning.message);
}

/** Restricts the discovered projects to a --only selection; an unknown name is a fatal, run-blocking diagnostic. */
function filterSelectedProjects(
    projects: ExtensionToolingProject[],
    selected: string[],
): { projects: ExtensionToolingProject[]; fatalDiagnostic?: string } {
    if (selected.length === 0) {
        return { projects };
    }

    // Resolve every requested name independently. A single unknown name fails
    // the whole run before any tool executes — a renamed/removed target must
    // never leave CI green while it is silently unchecked.
    const unmatched = selected.filter((name) => !projects.some((project) => matches(project, name)));

    if (unmatched.length > 0) {
        const available = projects.map((project) => project.name).join(', ');

        return {
            projects: [],
            fatalDiagnostic: `--only names unknown extension(s): ${unmatched.join(', ')}. Discovered: ${available || '(none)'}.`,
        };
    }

    return { projects: projects.filter((project) => selected.some((name) => matches(project, name))) };
}

export async function checkExtensions(options: CheckExtensionsOptions): Promise<CheckExtensionsResult> {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const commands = resolveToolingCommands(projectRoot, administrationRoot);
    const setupResult = setupExtensionTooling({ projectRoot, administrationRoot });
    const fatalDiagnostics = collectSetupDiagnostics(setupResult, commands);
    const selected = normalizeSelection(options.only);
    const selection = filterSelectedProjects(setupResult.manifest.projects, selected);
    const projects = selection.projects;
    const warnings: string[] = selectedSetupWarnings(setupResult.warnings, setupResult.manifest.projects, selected);

    if (selection.fatalDiagnostic) {
        fatalDiagnostics.push(selection.fatalDiagnostic);
    }

    const eslintBaseArguments =
        readEslintMajorVersion(administrationRoot) < 10
            ? [
                  '--flag',
                  'v10_config_lookup_from_file',
              ]
            : [];
    const maxWorkers = options.maxWorkers ?? Math.max(1, Math.min(4, os.cpus().length - 1));
    const limit = createLimiter(maxWorkers);
    const vueTscPath = path.join(administrationRoot, 'node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

    if (projects.length > 0 && !fs.existsSync(vueTscPath)) {
        const administrationRelative = relativePosix(projectRoot, administrationRoot);
        // In a Composer/Flex install the Administration lives under vendor/ and
        // its node_modules are not shipped — the monorepo `composer init:js`
        // does not apply there, so point the developer at `npm ci` instead.
        const fix = administrationRelative.startsWith('vendor/')
            ? `Fix: run "npm ci" in ${administrationRelative}`
            : 'Fix: composer init:js';

        fatalDiagnostics.push(
            `vue-tsc is not installed in the Administration (${relativePosix(projectRoot, vueTscPath)}). ${fix}`,
        );
    }

    const { resolvedModes } = await probeExtensionModes({
        projects,
        projectRoot,
        administrationRoot,
        eslintBaseArguments,
        maxWorkers,
        limit,
    });

    const checkJobs = resolvedModes.map(({ project, tsResolution, eslintResolution }) => async () => {
        const { result, warnings: projectWarnings } = await checkProject({
            project,
            tsResolution,
            eslintResolution,
            projectRoot,
            vueTscPath,
            eslintPath,
            eslintBaseArguments,
            entitySchemaAvailable: setupResult.manifest.entitySchemaAvailable,
            options,
            limit,
            commands,
        });

        warnings.push(...projectWarnings);

        return result;
    });
    const results = await runPool(checkJobs, maxWorkers);
    const baselineUpdates: string[] = [];

    // Record the current findings as the baseline. Only meaningful once the full
    // TypeScript surface ran, so it is skipped while the schema is missing.
    if (options.updateBaseline && setupResult.manifest.entitySchemaAvailable) {
        for (const result of results) {
            const recorded = recordProjectBaseline(result, projectRoot, commands);

            fatalDiagnostics.push(...recorded.fatalDiagnostics);
            warnings.push(...recorded.warnings);
            baselineUpdates.push(...recorded.baselineUpdates);
        }
    }

    return {
        results,
        fatalDiagnostics,
        warnings,
        baselineUpdates,
        exitCode: computeExitCode(results, options, fatalDiagnostics.length > 0),
    };
}

const CHECK_COMMAND: CommandSpec = {
    command: 'admin:check-extensions',
    description: "Type-check and lint Administration extensions with the Administration's own pinned toolchain.",
    flags: [
        {
            name: '--only',
            value: 'required',
            valueName: '<name[,name]>',
            description: 'Check only the named extensions (skips the interactive picker).',
        },
        { name: '--all', description: 'Check every discovered extension (skips the interactive picker).' },
        { name: '--strict-vendor', description: 'Fail on findings in vendor-installed (read-only) extensions.' },
        {
            name: '--fail-on-skipped',
            description: 'Fail (exit 1) when a writable extension is skipped/blocked instead of checked (for CI).',
        },
        {
            name: '--fix',
            description:
                'Apply ESLint autofixes, incl. Shopware deprecation codemods (sw-* → mt-*), not only formatting ' +
                '(vendor extensions only when named via --only).',
        },
        {
            name: '--update-baseline',
            description: 'Record the current findings as the per-plugin baseline; the check then fails only on new ones.',
        },
        { name: '--show-commands', description: 'Print the underlying vue-tsc/ESLint invocation per extension.' },
        { name: '--verbose', description: 'Also print tool output for passing and skipped extensions.' },
        {
            name: '--max-workers',
            value: 'required',
            valueName: '<n>',
            description: 'Bound the number of parallel tool runs.',
        },
        {
            name: '--project-root',
            value: 'required',
            valueName: '<path>',
            description:
                'Shop root to check (defaults to the PROJECT_ROOT env). Set this when running from a ' +
                'Composer/Flex install where the Administration lives under vendor/.',
        },
        {
            name: '--administration-root',
            value: 'required',
            valueName: '<path>',
            description: 'Administration app root (defaults to the installed one running this script).',
        },
    ],
};

/** Runs the check command; returns the process exit code (0 ok, 1 findings/error, 2 usage error). */
export async function runCheckCli(argv: string[]): Promise<number> {
    let parsed;

    try {
        parsed = parseCli(argv, CHECK_COMMAND);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(CHECK_COMMAND)}`);

            return 2;
        }

        throw error;
    }

    if (parsed.help) {
        console.log(renderHelp(CHECK_COMMAND));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        console.error(`PROJECT_ROOT or --project-root is required.\n\n${renderHelp(CHECK_COMMAND)}`);

        return 2;
    }

    const maxWorkersValue = parsed.values['--max-workers'];
    const maxWorkers = maxWorkersValue === undefined ? undefined : Number(maxWorkersValue);

    if (maxWorkers !== undefined && (!Number.isInteger(maxWorkers) || maxWorkers < 1)) {
        console.error(`--max-workers must be a positive integer, got "${maxWorkersValue}".\n\n${renderHelp(CHECK_COMMAND)}`);

        return 2;
    }

    if (parsed.flags.has('--update-baseline') && parsed.flags.has('--fix')) {
        console.error(
            `--update-baseline and --fix are mutually exclusive — fix first, then record the baseline.\n\n${renderHelp(CHECK_COMMAND)}`,
        );

        return 2;
    }

    const only = parsed.values['--only'];
    // Resolved once: the same list drives both the run's selection and the
    // explicit-name set that --fix uses to decide whether a vendor extension
    // was named on purpose.
    const explicitOnly = only === undefined ? [] : normalizeSelection(only);
    let selection: string[] | undefined;

    if (only !== undefined) {
        selection = explicitOnly;
    } else if (parsed.flags.has('--all')) {
        selection = undefined;
    } else if (process.stdin.isTTY && process.stdout.isTTY) {
        const resolvedRoot = path.resolve(projectRoot);
        const projects = discoverProjects(resolvedRoot, administrationRoot, pluginsConfigPath(resolvedRoot));

        if (projects.length === 0) {
            console.log('No Administration extensions discovered.');

            return 0;
        }

        const choice = await promptSelection(projects);

        if (choice === 'cancel') {
            console.log('Nothing selected.');

            return 0;
        }

        selection = choice === 'all' ? undefined : choice.names;
    } else {
        console.log(
            'Not an interactive terminal and no selection given — checking all extensions. ' +
                'Pass --only=<name[,name]> or --all to be explicit.',
        );
    }

    const check = await checkExtensions({
        projectRoot,
        administrationRoot,
        only: selection,
        strictVendor: parsed.flags.has('--strict-vendor'),
        maxWorkers,
        fix: parsed.flags.has('--fix'),
        explicitOnly,
        updateBaseline: parsed.flags.has('--update-baseline'),
        failOnSkipped: parsed.flags.has('--fail-on-skipped'),
    });

    console.log(
        renderCheckReport(check, {
            verbose: parsed.flags.has('--verbose'),
            showCommands: parsed.flags.has('--show-commands'),
            failOnSkipped: parsed.flags.has('--fail-on-skipped'),
            fix: parsed.flags.has('--fix'),
            commands: resolveToolingCommands(path.resolve(projectRoot), administrationRoot),
        }),
    );

    return check.exitCode;
}

if (require.main === module) {
    runCheckCli(process.argv.slice(2)).then(
        (exitCode) => {
            process.exitCode = exitCode;
        },
        (error: unknown) => {
            console.error(error instanceof Error ? error.message : error);
            process.exitCode = 1;
        },
    );
}

/**
 * @sw-package framework
 *
 * Orchestrator and CLI entrypoint for the Administration extension tooling
 * ("Connected Toolchain"). The generation steps live in sibling modules:
 * discovery (setup-discovery), the config generators (setup-configs), the
 * shim/bridge cluster (setup-bridge), and the .gitignore block
 * (setup-gitignore); all share the write-accounting context in setup-context.
 * The previously public discovery API is re-exported here so `./setup` stays
 * the single import for consumers.
 *
 * Pipeline: discover extensions (var/plugins.json) → per-extension leaf
 * tsconfigs in var/admin-extension-tooling/ → root tsconfig/eslint
 * projections → IDE bootstraps → optional per-plugin shims → manifest.
 *
 * Marker-owned files (root configs, IDE bootstraps, shims) a human wrote are
 * never overwritten; collisions are reported with integration instructions
 * instead. The var/ state files (leaf configs, manifest.json) are tool-owned
 * and rewritten on every run.
 */

import fs from 'fs';
import path from 'path';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { renderSetupReport } from './report';
import {
    STATE_DIR,
    readEslintMajorVersion,
    readPreviousManifest,
    relativePosix,
    resolveToolingCommands,
    writeStateFile,
} from './shared';
import type { ExtensionToolingManifest, ExtensionToolingProject, WriteResult } from './shared';
import { record, toManifestState } from './setup-context';
import type { GeneratorContext } from './setup-context';
import { checkDiscoveryFreshness, discoverProjects } from './setup-discovery';
import {
    createIdeBootstraps,
    createLeafConfigs,
    createRootEslintConfig,
    createRootTsconfig,
    ensureEntitySchema,
} from './setup-configs';
import { createShims } from './setup-bridge';
import { ensureGitignoreBlock } from './setup-gitignore';

// Barrel: discovery moved to setup-discovery but stays importable from ./setup.
export { checkDiscoveryFreshness, discoverProjects };

export interface SetupExtensionToolingOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    /** Technical name, extension name, or "all-custom": generate committed-config shims. */
    shim?: string;
    /**
     * Root-config bridge mode for a multi-root extension: the directory
     * (relative to the extension root) whose package-level config governs every
     * Administration root. One bridge is generated beside it instead of one per
     * root. Ignored unless `shim` names a single extension.
     */
    rootConfig?: string;
    /** Validate-only mode: report what would change, write nothing. */
    checkOnly?: boolean;
    /** Never touch the project .gitignore (persisted in the manifest). */
    noGitignore?: boolean;
}

export interface SetupExtensionToolingResult {
    manifest: ExtensionToolingManifest;
    manifestPath: string;
    writes: WriteResult[];
    /** Stale leaf configs of removed extensions that were (or would be) deleted. */
    staleFiles: string[];
    warnings: string[];
    /** Human instructions for user-owned files and IDEs we never write to. */
    instructions: string[];
    /** True when anything was (or would be) created, updated, or deleted. */
    changed: boolean;
    /** The var/plugins.json discovery source and its last-modified time, for `--explain`. */
    discoverySource: { path: string; updatedAt: string | null };
}

/** Reads the host-module map and warns about any declared module missing from the installed Administration. */
function loadHostModules(context: GeneratorContext): Record<string, string> {
    const hostModulesPath = path.join(context.toolingRoot, 'host-modules.json');
    const hostModules = (JSON.parse(fs.readFileSync(hostModulesPath, 'utf8')) as { hostModules: Record<string, string> })
        .hostModules;

    for (const [
        moduleName,
        modulePath,
    ] of Object.entries(hostModules)) {
        if (!fs.existsSync(path.join(context.administrationRoot, modulePath))) {
            context.warnings.push(
                `Host module "${moduleName}" is declared in host-modules.json but ${modulePath} does not exist ` +
                    'in the installed Administration.',
            );
        }
    }

    return hostModules;
}

export function setupExtensionTooling(options: SetupExtensionToolingOptions): SetupExtensionToolingResult {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const pluginsConfigPath = path.resolve(projectRoot, options.pluginsConfigPath ?? path.join('var', 'plugins.json'));
    const context: GeneratorContext = {
        projectRoot,
        administrationRoot,
        toolingRoot: path.join(administrationRoot, 'extension-tooling'),
        dryRun: options.checkOnly === true,
        commands: resolveToolingCommands(projectRoot, administrationRoot),
        writes: [],
        staleFiles: [],
        warnings: [],
        instructions: [],
    };

    const hostModules = loadHostModules(context);
    const entitySchemaAvailable = ensureEntitySchema(context);
    const freshnessWarning = checkDiscoveryFreshness(projectRoot, pluginsConfigPath);

    if (freshnessWarning) {
        context.warnings.push(freshnessWarning);
    }

    let discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);

    if (options.shim) {
        // Write the shim before leaf/root configs, then re-discover so the bridged
        // plugin is already recognized as shim-managed within this same run.
        createShims(context, discovered, options.shim, options.rootConfig);
        discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);
    }

    const projects = createLeafConfigs(context, discovered);
    const rootTsconfigState = createRootTsconfig(context, projects);
    const rootEslintState = createRootEslintConfig(context, projects);
    const adminRelative = relativePosix(projectRoot, administrationRoot);
    const ideBootstraps = createIdeBootstraps(context, adminRelative, readEslintMajorVersion(administrationRoot));
    const previousManifest = readPreviousManifest(projectRoot);
    const gitignore = ensureGitignoreBlock(
        context,
        previousManifest?.gitignore?.optedOut === true,
        previousManifest?.gitignore?.state,
        options.noGitignore === true,
    );

    if (!entitySchemaAvailable) {
        context.warnings.push(
            'Entity schema types are not available — entity names cannot be type-checked. ' +
                `Run \`${context.commands.generateSchema}\`.`,
        );
    }

    const manifest: ExtensionToolingManifest = {
        version: 3,
        adminRoot: adminRelative,
        entitySchemaAvailable,
        hostModules,
        rootConfigs: {
            tsconfig: toManifestState(rootTsconfigState),
            eslintConfig: toManifestState(rootEslintState),
        },
        ideBootstraps: Object.fromEntries(
            Object.entries(ideBootstraps).map(
                ([
                    key,
                    state,
                ]) => [
                    key,
                    toManifestState(state),
                ],
            ),
        ),
        gitignore,
        projects,
    };

    const manifestPath = path.join(projectRoot, STATE_DIR, 'manifest.json');

    record(context, writeStateFile(manifestPath, `${JSON.stringify(manifest, null, 4)}\n`, context.dryRun));

    const changed =
        context.staleFiles.length > 0 ||
        context.writes.some((write) => write.state === 'created' || write.state === 'updated');

    return {
        manifest,
        manifestPath,
        writes: context.writes,
        staleFiles: context.staleFiles,
        warnings: context.warnings,
        instructions: context.instructions,
        changed,
        discoverySource: {
            path: relativePosix(projectRoot, pluginsConfigPath),
            updatedAt: readMtimeIso(pluginsConfigPath),
        },
    };
}

/** ISO mtime of a file, or null if it cannot be read. */
function readMtimeIso(filePath: string): string | null {
    try {
        return fs.statSync(filePath).mtime.toISOString();
    } catch {
        return null;
    }
}

const SETUP_COMMAND: CommandSpec = {
    command: 'admin:setup-extension-tooling',
    description: 'Generate TypeScript/ESLint configs and IDE bootstraps for installed Administration extensions.',
    flags: [
        { name: '--check', description: 'Report what would change, write nothing. Exit 1 on drift.' },
        {
            name: '--explain',
            description: 'Read-only verbose report: discovered extensions, file list, IDE setup (writes nothing).',
        },
        { name: '--no-gitignore', description: 'Never manage the ignore block in the project .gitignore.' },
        {
            name: '--shim',
            value: 'required',
            valueName: '<TechnicalName>|all-custom',
            description:
                'Bridge one extension (or "all-custom") with committed configs that extend a generated ' +
                '.shopware-admin/ bridge.',
        },
        {
            name: '--root-config',
            value: 'required',
            valueName: '<dir>',
            description:
                'With --shim=<name>: bridge a multi-root extension once beside the package-level config in <dir> ' +
                '(relative to the extension root) instead of once per root.',
        },
        {
            name: '--project-root',
            value: 'required',
            valueName: '<path>',
            description:
                'Shop root to set up (defaults to the PROJECT_ROOT env). Set this when running from a ' +
                'Composer/Flex install where the Administration lives under vendor/.',
        },
        {
            name: '--administration-root',
            value: 'required',
            valueName: '<path>',
            description: 'Administration app root (defaults to the installed one running this script).',
        },
        {
            name: '--plugins-config',
            value: 'required',
            valueName: '<path>',
            description: 'Path to the bundle dump (defaults to var/plugins.json under the project root).',
        },
    ],
};

/** Runs the setup command; returns the process exit code (0 ok, 1 drift/error, 2 usage error). */
export function runSetupCli(argv: string[]): number {
    let parsed;

    try {
        parsed = parseCli(argv, SETUP_COMMAND);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(SETUP_COMMAND)}`);

            return 2;
        }

        throw error;
    }

    if (parsed.help) {
        console.log(renderHelp(SETUP_COMMAND));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        console.error(`PROJECT_ROOT or --project-root is required.\n\n${renderHelp(SETUP_COMMAND)}`);

        return 2;
    }

    const checkFlag = parsed.flags.has('--check');
    const explain = parsed.flags.has('--explain');
    const shim = parsed.values['--shim'];
    const rootConfig = parsed.values['--root-config'];
    // --explain is a read-only inspection view: it writes nothing (only its
    // details differ from --check). Unlike --check it does not gate on drift —
    // a human inspecting the setup should not get a surprise non-zero exit.
    const readOnly = checkFlag || explain;

    if (rootConfig !== undefined && shim === undefined) {
        console.error(`--root-config only applies together with --shim=<name>.\n\n${renderHelp(SETUP_COMMAND)}`);

        return 2;
    }

    try {
        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: parsed.values['--plugins-config'],
            shim,
            rootConfig,
            checkOnly: readOnly,
            noGitignore: parsed.flags.has('--no-gitignore'),
        });

        console.log(
            renderSetupReport(result, {
                explain,
                checkOnly: readOnly,
                shim,
                // The plain run is where flags are discovered — and where a flag
                // swallowed by composer (missing "--") would have landed.
                showFlagHint: !readOnly && !shim,
                commands: resolveToolingCommands(path.resolve(projectRoot), administrationRoot),
            }),
        );

        return checkFlag && result.changed ? 1 : 0;
    } catch (error) {
        console.error(error instanceof Error ? error.message : error);

        return 1;
    }
}

if (require.main === module) {
    process.exitCode = runSetupCli(process.argv.slice(2));
}

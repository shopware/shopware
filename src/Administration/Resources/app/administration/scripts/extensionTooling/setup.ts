/**
 * @sw-package framework
 *
 * EXPERIMENTAL — the whole extension tooling is shipped for feedback and is not
 * covered by the backwards-compatibility promise. Nothing here is a stable
 * contract: the command name, its flags, the module boundaries below, the layout
 * of the generated files and the manifest schema can all change in any release,
 * so the implementation can be reshaped once real-world usage shows what does
 * not hold. Consumers are expected to re-run setup rather than to depend on the
 * generated output, which is why the manifest is versioned and every generated
 * file is either tool-owned or marker-owned. Deliberately no `stableVersion` is
 * declared yet — the marker is dropped from the code, the README and the release
 * note together once the surfaces have settled.
 *
 * Orchestrator and CLI entrypoint for the Administration extension tooling
 * ("Connected Toolchain"). The generation steps live in sibling modules:
 * discovery (setup-discovery), the config generators (setup-configs), the
 * bridge cluster (setup-bridge), and the .gitignore block (setup-gitignore);
 * all share the write-accounting context in setup-context. The previously
 * public discovery API is re-exported here so `./setup` stays the single
 * import for consumers.
 *
 * Pipeline: discover extensions (var/plugins.json) → automatic per-extension
 * bridges + scaffolded configs → fallback leaf tsconfigs in
 * var/admin-extension-tooling/ for targets the bridge could not cover → root
 * tsconfig/eslint projections → IDE bootstraps → manifest.
 *
 * Marker-owned files (root configs, IDE bootstraps, bridges) a human wrote are
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
import type { ExtensionToolingManifest, WriteResult } from './shared';
import { record, toManifestState } from './setup-context';
import type { GeneratorContext } from './setup-context';
import { discoverProjects } from './setup-discovery';
import {
    createIdeBootstraps,
    createLeafConfigs,
    createRootEslintConfig,
    createRootTsconfig,
    ensureEntitySchema,
} from './setup-configs';
import { createBridges } from './setup-bridge';
import { ensureGitignoreBlock } from './setup-gitignore';

// Barrel: discovery moved to setup-discovery but stays importable from ./setup.
export { discoverProjects };

export interface SetupExtensionToolingOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    /**
     * Root-config bridge mode for a multi-root extension: the directory
     * (relative to the extension root) whose package-level config governs every
     * Administration root. One bridge is generated beside it instead of one per
     * root. The choice is persisted in the manifest, so plain re-runs keep it.
     */
    rootConfig?: { extension: string; dir: string };
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
    const previousManifest = readPreviousManifest(projectRoot);

    let discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);

    // Persisted root-config choices survive plain re-runs; entries of removed
    // extensions are dropped, a fresh --root-config value wins for its extension.
    const discoveredNames = new Set(discovered.map((project) => project.name));
    const rootConfigDirs: Record<string, string> = Object.fromEntries(
        Object.entries(previousManifest?.rootConfigDirs ?? {}).filter(([name]) => discoveredNames.has(name)),
    );

    if (options.rootConfig) {
        if (discoveredNames.has(options.rootConfig.extension)) {
            rootConfigDirs[options.rootConfig.extension] = options.rootConfig.dir;
        } else {
            context.warnings.push(
                `--root-config names the unknown extension ${options.rootConfig.extension}. ` +
                    `Discovered extensions: ${[...discoveredNames].sort().join(', ') || '(none)'}.`,
            );
        }
    }

    // Bridge before leaf/root configs, then re-discover so every freshly
    // bridged extension is already recognized as bridged within this same run.
    // (A --check dry-run cannot see unwritten bridges, so it lists the fallback
    // leafs a real run would not produce — the exit code is 1 either way.)
    createBridges(context, discovered, rootConfigDirs);
    discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);

    const projects = createLeafConfigs(context, discovered);
    const rootTsconfigState = createRootTsconfig(context, projects);
    const rootEslintState = createRootEslintConfig(context, projects);
    const adminRelative = relativePosix(projectRoot, administrationRoot);
    const ideBootstraps = createIdeBootstraps(context, adminRelative, readEslintMajorVersion(administrationRoot));
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
        rootConfigDirs,
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
    };
}

const SETUP_COMMAND: CommandSpec = {
    command: 'admin:setup-extension-tooling',
    // The help text is the flag reference, so the BC caveat belongs on the flags
    // themselves, not only on the report a run prints.
    description:
        'Generate TypeScript/ESLint configs and IDE bootstraps for installed Administration extensions.\n' +
        'EXPERIMENTAL: not covered by the backwards-compatibility promise — the command name, the flags\n' +
        'below, the generated-file layout and the manifest format can change in any release.',
    flags: [
        { name: '--check', description: 'Report what would change, write nothing. Exit 1 on drift.' },
        { name: '--no-gitignore', description: 'Never manage the ignore block in the project .gitignore.' },
        {
            name: '--root-config',
            value: 'required',
            valueName: '<Extension>:<dir>',
            description:
                'Bridge the named multi-root extension once beside the package-level config in <dir> ' +
                '(relative to the extension root) instead of once per root. Persisted for later plain runs.',
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
    const rootConfigValue = parsed.values['--root-config'];
    let rootConfig: { extension: string; dir: string } | undefined;

    if (rootConfigValue !== undefined) {
        const separatorIndex = rootConfigValue.indexOf(':');

        if (separatorIndex <= 0 || separatorIndex === rootConfigValue.length - 1) {
            console.error(
                `--root-config expects <Extension>:<dir>, got "${rootConfigValue}".\n\n${renderHelp(SETUP_COMMAND)}`,
            );

            return 2;
        }

        rootConfig = {
            extension: rootConfigValue.slice(0, separatorIndex),
            dir: rootConfigValue.slice(separatorIndex + 1),
        };
    }

    try {
        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: parsed.values['--plugins-config'],
            rootConfig,
            checkOnly: checkFlag,
            noGitignore: parsed.flags.has('--no-gitignore'),
        });

        console.log(
            renderSetupReport(result, {
                checkOnly: checkFlag,
                // The plain run is where flags are discovered — and where a flag
                // swallowed by composer (missing "--") would have landed.
                showFlagHint: !checkFlag,
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

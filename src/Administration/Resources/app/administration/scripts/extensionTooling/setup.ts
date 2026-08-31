/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING
 *
 * The whole extension tooling is shipped for feedback and is not covered by
 * the backwards-compatibility promise until it stabilizes (targeted for
 * v6.8.0). Nothing here is a stable contract: the command name, its flags, the
 * module boundaries below, the layout of the generated files and the manifest
 * schema can all change in any release, so the implementation can be reshaped
 * once real-world usage shows what does not hold. Consumers are expected to
 * re-run setup rather than to depend on the generated output, which is why the
 * manifest is versioned and every generated file is either tool-owned or
 * marker-owned.
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
 * bridges + scaffolded configs → re-discover → root tsconfig/eslint
 * projections covering whatever no extension config governs → IDE bootstraps →
 * manifest.
 *
 * Marker-owned files (root configs, IDE bootstraps, bridges) a human wrote are
 * never overwritten; collisions are reported with integration instructions
 * instead. The manifest under var/ is tool-owned and rewritten on every run.
 */

import fs from 'fs';
import path from 'path';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { renderSetupReport } from './report';
import {
    STATE_DIR,
    pluginsConfigPath,
    readEslintMajorVersion,
    readPreviousManifest,
    relativePosix,
    resolveToolingCommands,
    writeStateFile,
} from './shared';
import type { ExtensionToolingManifest, SetupWarning, WriteResult } from './shared';
import { record, toManifestState, warn } from './setup-context';
import type { GeneratorContext } from './setup-context';
import { discoverProjects } from './setup-discovery';
import { createIdeBootstraps, createRootEslintConfig, createRootTsconfig, ensureEntitySchema } from './setup-configs';
import { createBridges } from './setup-bridge';
import { ensureGitignoreBlock } from './setup-gitignore';

// Barrel: discovery moved to setup-discovery but stays importable from ./setup.
export { discoverProjects };

export interface SetupExtensionToolingOptions {
    projectRoot: string;
    administrationRoot: string;
    /**
     * Bridge one extension beside the package-level config in this directory
     * (relative to the extension root) instead of beside each of its owned
     * configs — for a multi-root package whose layout the grouping cannot infer.
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
    /** Files of removed extensions that were (or would be) deleted. */
    staleFiles: string[];
    warnings: SetupWarning[];
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
            warn(
                context,
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
    const bundleDumpPath = pluginsConfigPath(projectRoot);
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

    let discovered = discoverProjects(projectRoot, administrationRoot, bundleDumpPath);
    const rootConfigDirs: Record<string, string> = {};

    if (options.rootConfig) {
        const known = discovered.some((project) => project.name === options.rootConfig?.extension);

        if (known) {
            rootConfigDirs[options.rootConfig.extension] = options.rootConfig.dir;
        } else {
            warn(
                context,
                `--root-config names the unknown extension ${options.rootConfig.extension}. ` +
                    `Discovered extensions: ${
                        discovered
                            .map((project) => project.name)
                            .sort()
                            .join(', ') || '(none)'
                    }.`,
            );
        }
    }

    // Bridge before the root projection, then re-discover so every freshly
    // bridged extension is already recognized as bridged within this same run.
    // (A --check dry-run cannot see unwritten bridges, so it reports the
    // projection a real run would not need — the exit code is 1 either way.)
    createBridges(context, discovered, rootConfigDirs);
    discovered = discoverProjects(projectRoot, administrationRoot, bundleDumpPath);

    const rootTsconfigState = createRootTsconfig(context, discovered);
    const rootEslintState = createRootEslintConfig(context, discovered);
    const adminRelative = relativePosix(projectRoot, administrationRoot);
    const ideBootstraps = createIdeBootstraps(context, adminRelative, readEslintMajorVersion(administrationRoot));
    const gitignore = ensureGitignoreBlock(
        context,
        previousManifest?.gitignore?.optedOut === true,
        previousManifest?.gitignore?.state,
        options.noGitignore === true,
    );

    if (!entitySchemaAvailable) {
        warn(
            context,
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
        projects: discovered,
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
            name: '--if-enabled',
            description:
                'Run only when the ADMIN_EXTENSION_TOOLING feature flag is enabled (e.g. in .env); otherwise ' +
                'exit 0 without touching anything. Used by the `composer setup` chain.',
        },
        {
            name: '--root-config',
            value: 'required',
            valueName: '<Extension>:<dir>',
            description:
                'Bridge the named extension once beside the package-level config in <dir> (relative to the ' +
                'extension root) instead of beside each config it owns.',
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
    ],
};

/**
 * Mirrors PHP Feature::isTrue ("$value && $value !== 'false'"): enabled when
 * non-empty and neither "0" (falsy as a PHP string) nor the literal "false".
 * Read from the raw env because this runs before any PHP bootstrap — the
 * composer scripts load .env via bin/exec-with-env.
 */
function isFeatureFlagEnabled(value: string | undefined): boolean {
    return value !== undefined && value !== '' && value !== '0' && value !== 'false';
}

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

    // Before any root resolution, so a disabled flag can never fail on a
    // missing PROJECT_ROOT — the skip must be a guaranteed no-op.
    if (parsed.flags.has('--if-enabled') && !isFeatureFlagEnabled(process.env.ADMIN_EXTENSION_TOOLING)) {
        console.log(
            'Administration extension tooling: skipped — set ADMIN_EXTENSION_TOOLING=1 (e.g. in .env) to run ' +
                'this experimental step during composer setup.',
        );

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

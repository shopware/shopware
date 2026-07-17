/**
 * @sw-package framework
 *
 * Generator for the Administration extension tooling ("Connected Toolchain").
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
import {
    GENERATED_MARKER,
    MANAGED_BLOCK_BEGIN,
    SHIM_DIR_NAME,
    STATE_DIR,
    asRelativeSpecifier,
    findExtensionRoot,
    findNearestConfig,
    isGeneratedContent,
    isWithin,
    readBundleConfig,
    readPreviousManifest,
    relativePosix,
    toPosix,
    writeManagedBlock,
    writeManagedFile,
    writeScaffoldFile,
    writeStateFile,
} from './shared';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import type {
    ExtensionToolingManifest,
    ExtensionToolingProject,
    ManagedFileState,
    ManifestFileState,
    WriteResult,
} from './shared';
import {
    readProbeCache,
    resolveModesFromCache,
    resolveStaticEslintMode,
    resolveStaticTsMode,
    writeProbeCache,
} from './probe';
import { renderSetupReport } from './report';

const ESLINT_CONFIG_NAMES = [
    'eslint.config.mjs',
    'eslint.config.js',
    'eslint.config.cjs',
    'eslint.config.ts',
];
const SOURCE_EXTENSIONS = [
    'ts',
    'tsx',
    'vue',
    'js',
];
/**
 * Test files are split off from the runtime program (whose preset sets
 * `types: []`, so its runner globals are absent) into a dedicated spec program
 * that adds jest types. The runtime leaf excludes these suffixes; the spec leaf
 * includes exactly them.
 */
const SPEC_FILE_SUFFIXES = [
    'spec.ts',
    'spec.tsx',
    'spec.js',
];

export interface SetupExtensionToolingOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    /** Technical name, extension name, or "all-custom": generate committed-config shims. */
    shim?: string;
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

interface GeneratorContext {
    projectRoot: string;
    administrationRoot: string;
    toolingRoot: string;
    dryRun: boolean;
    writes: WriteResult[];
    staleFiles: string[];
    warnings: string[];
    instructions: string[];
}

function record(context: GeneratorContext, result: WriteResult): ManagedFileState {
    // Reported project-root-relative so every consumer renders portable paths.
    context.writes.push({
        ...result,
        file: path.isAbsolute(result.file) ? relativePosix(context.projectRoot, result.file) : result.file,
    });

    return result.state;
}

function toManifestState(state: ManagedFileState): ManifestFileState {
    return state === 'conflict' || state === 'skipped' ? state : 'managed';
}

function safeFileName(name: string): string {
    return name.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
}

export function discoverProjects(
    projectRoot: string,
    administrationRoot: string,
    pluginsConfigPath: string,
): ExtensionToolingProject[] {
    const bundles = readBundleConfig(pluginsConfigPath);
    const administrationSourcePath = path.resolve(administrationRoot, 'src');
    const vendorRoot = path.join(projectRoot, 'vendor');

    interface ProjectGroup {
        extensionRoot: string;
        technicalNames: Set<string>;
        sourcePaths: Set<string>;
    }

    const groups = new Map<string, ProjectGroup>();

    for (const bundle of bundles) {
        const administrationPath = bundle.administration?.path;

        if (!administrationPath) {
            continue;
        }

        const bundleBasePath = path.isAbsolute(bundle.basePath)
            ? path.normalize(bundle.basePath)
            : path.resolve(projectRoot, bundle.basePath);
        const sourcePath = path.resolve(bundleBasePath, administrationPath);

        if (!fs.existsSync(sourcePath) || sourcePath === administrationSourcePath) {
            continue;
        }

        const extensionRoot = findExtensionRoot(projectRoot, bundleBasePath);
        const group = groups.get(extensionRoot) ?? {
            extensionRoot,
            technicalNames: new Set<string>(),
            sourcePaths: new Set<string>(),
        };

        group.technicalNames.add(bundle.technicalName);
        group.sourcePaths.add(sourcePath);
        groups.set(extensionRoot, group);
    }

    const usedNames = new Map<string, number>();

    return [...groups.values()]
        .sort((left, right) => left.extensionRoot.localeCompare(right.extensionRoot))
        .map((group) => {
            const baseName = path.basename(group.extensionRoot);
            const nameCount = usedNames.get(baseName) ?? 0;

            usedNames.set(baseName, nameCount + 1);

            const name = nameCount === 0 ? baseName : `${path.basename(path.dirname(group.extensionRoot))}-${baseName}`;
            const sourcePaths = [...group.sourcePaths].sort();
            const tsconfig =
                sourcePaths
                    .map((source) => findNearestConfig(source, group.extensionRoot, ['tsconfig.json']))
                    .find((configPath) => configPath !== null) ?? null;
            const eslintConfig =
                sourcePaths
                    .map((source) => findNearestConfig(source, group.extensionRoot, ESLINT_CONFIG_NAMES))
                    .find((configPath) => configPath !== null) ?? null;
            const bridgePresent = sourcePaths.some((source) =>
                fs.existsSync(path.join(path.dirname(source), SHIM_DIR_NAME, 'tsconfig.json')),
            );

            return {
                name,
                technicalNames: [...group.technicalNames].sort(),
                basePath: relativePosix(projectRoot, group.extensionRoot),
                sourcePaths: sourcePaths.map((source) => relativePosix(projectRoot, source)),
                vendor: isWithin(group.extensionRoot, vendorRoot),
                bridgePresent,
                tsconfig: tsconfig ? relativePosix(projectRoot, tsconfig) : null,
                eslintConfig: eslintConfig ? relativePosix(projectRoot, eslintConfig) : null,
                ts: resolveStaticTsMode(tsconfig),
                eslint: resolveStaticEslintMode(eslintConfig),
                checkTsconfig: '',
                specTsconfig: '',
            };
        });
}

/**
 * Discovery reads var/plugins.json, which only `bin/console bundle:dump`
 * refreshes — neither plugin:install nor cache:clear do. A freshly activated
 * plugin is invisible until then, so a stale file earns a hint instead of a
 * silently green "up to date". Heuristic: false positives cost one dim line.
 */
export function checkDiscoveryFreshness(projectRoot: string, pluginsConfigPath: string): string | null {
    try {
        const pluginsMtime = fs.statSync(pluginsConfigPath).mtimeMs;
        const customPluginsDir = path.join(projectRoot, 'custom', 'plugins');
        let newestPluginMtime = 0;

        for (const entry of fs.readdirSync(customPluginsDir, { withFileTypes: true })) {
            if (!entry.isDirectory()) {
                continue;
            }

            try {
                newestPluginMtime = Math.max(
                    newestPluginMtime,
                    fs.statSync(path.join(customPluginsDir, entry.name, 'composer.json')).mtimeMs,
                );
            } catch {
                // A plugin folder without composer.json cannot be discovered anyway.
            }
        }

        if (newestPluginMtime > pluginsMtime) {
            return (
                'var/plugins.json is older than custom/plugins/ — if an extension is missing below, ' +
                'run: bin/console bundle:dump'
            );
        }
    } catch {
        // Missing plugins.json or custom/plugins/ — discovery itself reports that.
    }

    return null;
}

function ensureEntitySchema(context: GeneratorContext): boolean {
    const entitySchemaPath = path.join(context.administrationRoot, 'src', 'entity-schema-definition.d.ts');

    if (fs.existsSync(entitySchemaPath)) {
        return !isGeneratedContent(fs.readFileSync(entitySchemaPath, 'utf8'));
    }

    const stubContent = [
        '/* eslint-disable */',
        `/* ${GENERATED_MARKER} — STUB.`,
        '   The installation-specific entity schema has not been generated yet, so',
        '   `EntitySchema.Entities` is empty and every entity name fails the type',
        '   check instead of silently degrading to `any`.',
        '   Generate the real file with: composer admin:generate-entity-schema-types */',
        'declare namespace EntitySchema {',
        '    interface Entities {}',
        '}',
        '',
    ].join('\n');

    record(context, writeManagedFile(entitySchemaPath, stubContent, context.dryRun));
    context.warnings.push(
        'The generated entity schema types are missing; a stub with an empty entity list was created. ' +
            'Run `composer admin:generate-entity-schema-types` to get installation-specific entity types.',
    );

    return false;
}

function createLeafConfigs(context: GeneratorContext, projects: ExtensionToolingProject[]): ExtensionToolingProject[] {
    const projectsDir = path.join(context.projectRoot, STATE_DIR, 'projects');
    const basePreset = path.join(context.administrationRoot, 'extension-tooling', 'tsconfig.base.json');
    const adminTypes = path.join(context.administrationRoot, 'extension-tooling', 'admin-types.d.ts');
    const specTypes = path.join(context.administrationRoot, 'extension-tooling', 'spec-types.d.ts');
    const usedFileNames = new Set<string>();

    const sourceGlobs = (configPath: string, sourcePath: string, extensions: string[]): string[] =>
        extensions.map(
            (extension) =>
                `${asRelativeSpecifier(configPath, path.resolve(context.projectRoot, sourcePath))}/**/*.${extension}`,
        );

    const configured = projects.map((project) => {
        let fileName = `${safeFileName(project.name)}.json`;
        let suffix = 2;

        while (usedFileNames.has(fileName)) {
            fileName = `${safeFileName(project.name)}-${suffix}.json`;
            suffix += 1;
        }

        const specFileName = fileName.replace(/\.json$/, '-specs.json');

        usedFileNames.add(fileName);
        usedFileNames.add(specFileName);

        const configPath = path.join(projectsDir, fileName);
        const content = `// ${GENERATED_MARKER}\n${JSON.stringify(
            {
                extends: asRelativeSpecifier(configPath, basePreset),
                files: [asRelativeSpecifier(configPath, adminTypes)],
                include: project.sourcePaths.flatMap((sourcePath) => sourceGlobs(configPath, sourcePath, SOURCE_EXTENSIONS)),
                // Exclude patterns resolve relative to this config, so they
                // carry the same source prefix as the includes.
                exclude: project.sourcePaths.flatMap((sourcePath) =>
                    sourceGlobs(configPath, sourcePath, SPEC_FILE_SUFFIXES),
                ),
            },
            null,
            4,
        )}\n`;

        record(context, writeStateFile(configPath, content, context.dryRun));

        // Companion program for spec files: the same type surface plus jest
        // types (spec-types.d.ts), including only the specs the runtime program
        // excludes. Keeping the jest globals here keeps them out of the runtime
        // program, so runtime code cannot accidentally use describe/expect.
        const specConfigPath = path.join(projectsDir, specFileName);
        const specContent = `// ${GENERATED_MARKER}\n${JSON.stringify(
            {
                extends: asRelativeSpecifier(specConfigPath, basePreset),
                // Point typeRoots at the Administration's own @types so the jest
                // reference in spec-types.d.ts resolves — a triple-slash
                // reference is looked up relative to this config, not the .d.ts.
                compilerOptions: {
                    typeRoots: [
                        asRelativeSpecifier(specConfigPath, path.join(context.administrationRoot, 'node_modules', '@types')),
                    ],
                },
                files: [
                    asRelativeSpecifier(specConfigPath, adminTypes),
                    asRelativeSpecifier(specConfigPath, specTypes),
                ],
                include: project.sourcePaths.flatMap((sourcePath) =>
                    sourceGlobs(specConfigPath, sourcePath, SPEC_FILE_SUFFIXES),
                ),
            },
            null,
            4,
        )}\n`;

        record(context, writeStateFile(specConfigPath, specContent, context.dryRun));

        return {
            ...project,
            checkTsconfig: project.tsconfig ?? relativePosix(context.projectRoot, configPath),
            specTsconfig: relativePosix(context.projectRoot, specConfigPath),
        };
    });

    if (fs.existsSync(projectsDir)) {
        for (const existingFile of fs.readdirSync(projectsDir)) {
            if (existingFile.endsWith('.json') && !usedFileNames.has(existingFile)) {
                context.staleFiles.push(relativePosix(context.projectRoot, path.join(projectsDir, existingFile)));

                if (!context.dryRun) {
                    fs.rmSync(path.join(projectsDir, existingFile));
                }
            }
        }
    }

    return configured;
}

function createRootTsconfig(context: GeneratorContext, projects: ExtensionToolingProject[]): ManagedFileState {
    const rootTsconfigPath = path.join(context.projectRoot, 'tsconfig.json');
    const references = projects
        .filter((project) => project.ts.mode === 'managed')
        .map((project) => ({ path: `./${project.checkTsconfig}` }));
    const content = `// ${GENERATED_MARKER} — solution-style index routing each extension file to its leaf project.\n${JSON.stringify(
        {
            files: [],
            references,
        },
        null,
        4,
    )}\n`;
    const state = record(context, writeManagedFile(rootTsconfigPath, content, context.dryRun));

    if (state === 'conflict') {
        context.instructions.push(
            [
                `${rootTsconfigPath} exists and is not managed by this tool. To integrate, add these references:`,
                ...references.map((reference) => `    { "path": "${reference.path}" }`),
                'or remove the file and re-run `composer admin:setup-extension-tooling`.',
            ].join('\n'),
        );
    }

    return state;
}

function createRootEslintConfig(context: GeneratorContext, projects: ExtensionToolingProject[]): ManagedFileState {
    const rootEslintPath = path.join(context.projectRoot, 'eslint.config.mjs');
    const extensionRoots = projects.flatMap((project) => project.sourcePaths);

    // Without discovered extensions there is nothing to scope the config to.
    // The factory would then fall back to project-wide globs and lint files
    // outside the Administration (e.g. real server-side Twig), so the root
    // config is skipped entirely instead of written empty.
    if (extensionRoots.length === 0) {
        return 'skipped';
    }

    const factoryPath = path.join(context.administrationRoot, 'extension-tooling', 'eslint.mjs');
    const factorySpecifier = asRelativeSpecifier(rootEslintPath, factoryPath);
    const content = [
        `// ${GENERATED_MARKER}`,
        `import { shopwareAdminExtension } from ${JSON.stringify(factorySpecifier)};`,
        '',
        'export default shopwareAdminExtension({',
        '    tsconfigRootDir: import.meta.dirname,',
        '    extensionRoots: [',
        ...extensionRoots.map((extensionRoot) => `        ${JSON.stringify(extensionRoot)},`),
        '    ],',
        '});',
        '',
    ].join('\n');
    const state = record(context, writeManagedFile(rootEslintPath, content, context.dryRun));

    if (state === 'conflict') {
        context.instructions.push(
            [
                `${rootEslintPath} exists and is not managed by this tool. To integrate, compose the shared factory:`,
                `    import { shopwareAdminExtension } from ${JSON.stringify(factorySpecifier)};`,
                '    export default [',
                '        ...shopwareAdminExtension({ tsconfigRootDir: import.meta.dirname, extensionRoots: [/* … */] }),',
                '        // your own config',
                '    ];',
            ].join('\n'),
        );
    }

    return state;
}

function readEslintMajorVersion(administrationRoot: string): number {
    const eslintPackagePath = path.join(administrationRoot, 'node_modules', 'eslint', 'package.json');

    try {
        const eslintPackage = JSON.parse(fs.readFileSync(eslintPackagePath, 'utf8')) as { version: string };

        return Number(eslintPackage.version.split('.')[0]);
    } catch {
        return 9;
    }
}

function createIdeBootstraps(context: GeneratorContext, adminRelative: string): Record<string, ManagedFileState> {
    const states: Record<string, ManagedFileState> = {};
    const eslintFlags = readEslintMajorVersion(context.administrationRoot) < 10 ? ['v10_config_lookup_from_file'] : [];
    const bootstraps: Array<{ key: string; file: string; content: string; settings: string[] }> = [
        {
            key: '.vscode/settings.json',
            file: path.join(context.projectRoot, '.vscode', 'settings.json'),
            content: `// ${GENERATED_MARKER}\n${JSON.stringify(
                {
                    'typescript.tsdk': `${adminRelative}/node_modules/typescript/lib`,
                    'eslint.nodePath': `${adminRelative}/node_modules`,
                    ...(eslintFlags.length > 0 ? { 'eslint.options': { flags: eslintFlags } } : {}),
                    'eslint.validate': [
                        'javascript',
                        'typescript',
                        'vue',
                        'twig',
                    ],
                    'files.associations': { '*.html.twig': 'twig' },
                },
                null,
                4,
            )}\n`,
            settings: [
                `"typescript.tsdk": "${adminRelative}/node_modules/typescript/lib"`,
                `"eslint.nodePath": "${adminRelative}/node_modules"`,
                ...(eslintFlags.length > 0 ? [`"eslint.options": { "flags": ["${eslintFlags[0]}"] }`] : []),
            ],
        },
        {
            key: '.zed/settings.json',
            file: path.join(context.projectRoot, '.zed', 'settings.json'),
            content: `// ${GENERATED_MARKER}\n${JSON.stringify(
                {
                    lsp: {
                        vtsls: {
                            initialization_options: {
                                typescript: { tsdk: `${adminRelative}/node_modules/typescript/lib` },
                            },
                        },
                        eslint: {
                            settings: {
                                nodePath: `${adminRelative}/node_modules`,
                                ...(eslintFlags.length > 0 ? { options: { flags: eslintFlags } } : {}),
                            },
                        },
                    },
                },
                null,
                4,
            )}\n`,
            settings: [
                `"lsp.vtsls.initialization_options.typescript.tsdk": "${adminRelative}/node_modules/typescript/lib"`,
                `"lsp.eslint.settings.nodePath": "${adminRelative}/node_modules"`,
            ],
        },
    ];

    for (const bootstrap of bootstraps) {
        if (fs.existsSync(bootstrap.file) && !isGeneratedContent(fs.readFileSync(bootstrap.file, 'utf8'))) {
            states[bootstrap.key] = 'skipped';
            record(context, { file: bootstrap.file, state: 'skipped' });
            context.instructions.push(
                [
                    `${bootstrap.file} is user-owned and was not touched. For IDE support add:`,
                    ...bootstrap.settings.map((setting) => `    ${setting}`),
                ].join('\n'),
            );

            continue;
        }

        states[bootstrap.key] = record(context, writeManagedFile(bootstrap.file, bootstrap.content, context.dryRun));
    }

    // PhpStorm stores these settings in .idea internals we never write to.
    states['.idea'] = 'skipped';
    context.instructions.push(
        [
            'PhpStorm (configure once, Settings → Languages & Frameworks):',
            `    TypeScript → set "TypeScript" package to ${adminRelative}/node_modules/typescript`,
            '    JavaScript → Code Quality Tools → ESLint → Manual configuration:',
            `        ESLint package: ${adminRelative}/node_modules/eslint`,
            ...(readEslintMajorVersion(context.administrationRoot) < 10
                ? ['        Extra eslint options: --flag v10_config_lookup_from_file']
                : []),
        ].join('\n'),
    );

    return states;
}

const GITIGNORE_ENTRIES = [
    '/tsconfig.json',
    '/eslint.config.mjs',
    '/.zed/',
];

/**
 * Generated root files are untracked noise in a project whose .gitignore does
 * not cover them. Policy consistent with the ownership model: own a fenced
 * block, never rewrite user lines, respect a user's deletion of the block,
 * and stand down entirely when the entries are already covered (the platform
 * monorepo commits them itself) or after --no-gitignore.
 */
function ensureGitignoreBlock(
    context: GeneratorContext,
    previouslyOptedOut: boolean,
    previousState: ManifestFileState | undefined,
    noGitignore: boolean,
): { state: ManifestFileState; optedOut: boolean } {
    if (noGitignore || previouslyOptedOut) {
        return { state: 'skipped', optedOut: true };
    }

    const gitignorePath = path.join(context.projectRoot, '.gitignore');
    const currentContent = fs.existsSync(gitignorePath) ? fs.readFileSync(gitignorePath, 'utf8') : null;
    const hasBlock = currentContent !== null && currentContent.includes(MANAGED_BLOCK_BEGIN);

    if (currentContent !== null && !hasBlock) {
        const lines = currentContent.split(/\r?\n/).map((line) => line.trim());

        if (GITIGNORE_ENTRIES.every((entry) => lines.includes(entry))) {
            return { state: 'skipped', optedOut: false };
        }

        if (previousState === 'managed') {
            // The user deleted the managed block — their decision stands.
            context.instructions.push(
                'The managed ignore block was removed from .gitignore and is left alone. ' +
                    'Re-add it by re-running after deleting var/admin-extension-tooling/manifest.json, ' +
                    'or silence this with --no-gitignore.',
            );

            return { state: 'skipped', optedOut: false };
        }
    }

    const result = writeManagedBlock(
        gitignorePath,
        [
            '# Generated by composer admin:setup-extension-tooling — root projections and IDE bootstraps.',
            ...GITIGNORE_ENTRIES,
        ],
        context.dryRun,
    );

    record(context, result);

    if (result.state === 'conflict') {
        context.instructions.push(
            '.gitignore contains a malformed managed block (begin marker without end) and was not touched. ' +
                `Remove or complete the block starting with "${MANAGED_BLOCK_BEGIN}".`,
        );
    }

    return { state: toManifestState(result.state), optedOut: false };
}

/**
 * TypeScript replaces `paths` wholesale across `extends`, so a plugin that
 * declares its own aliases would erase the preset's host paths. The shim is
 * therefore the single `paths` declarer: it merges the preset's host paths
 * (re-relativized to the shim, machine paths are fine in generated files)
 * with optional plugin aliases from a committed
 * `tsconfig.aliases.json` next to the shim ({ "MyPlugin/*": ["src/*"] },
 * targets relative to the plugin's administration folder).
 */
function buildShimPaths(context: GeneratorContext, shimDir: string, adminFolder: string): Record<string, string[]> | null {
    const aliasesPath = path.join(adminFolder, 'tsconfig.aliases.json');

    if (!fs.existsSync(aliasesPath)) {
        return null;
    }

    const aliases = JSON.parse(fs.readFileSync(aliasesPath, 'utf8')) as Record<string, string[] | string>;
    const presetPath = path.join(context.toolingRoot, 'tsconfig.base.json');
    const preset = JSON.parse(fs.readFileSync(presetPath, 'utf8')) as {
        compilerOptions?: { paths?: Record<string, string[]> };
    };
    const presetDir = path.dirname(presetPath);
    const mergedPaths: Record<string, string[]> = {};

    for (const [
        moduleName,
        targets,
    ] of Object.entries(preset.compilerOptions?.paths ?? {})) {
        mergedPaths[moduleName] = targets.map((target) =>
            asRelativeSpecifier(path.join(shimDir, 'tsconfig.json'), path.resolve(presetDir, target)),
        );
    }

    for (const [
        alias,
        targets,
    ] of Object.entries(aliases)) {
        mergedPaths[alias] = (Array.isArray(targets) ? targets : [targets]).map((target) =>
            asRelativeSpecifier(path.join(shimDir, 'tsconfig.json'), path.resolve(adminFolder, target)),
        );
    }

    return mergedPaths;
}

function createShims(context: GeneratorContext, projects: ExtensionToolingProject[], shim: string): void {
    const targets =
        shim === 'all-custom'
            ? projects.filter((project) => project.basePath.startsWith('custom/plugins/'))
            : projects.filter((project) => project.name === shim || project.technicalNames.includes(shim));

    if (targets.length === 0) {
        const available = projects.map((project) => project.name).join(', ');

        throw new Error(`No extension matches --shim=${shim}. Discovered extensions: ${available || '(none)'}.`);
    }

    for (const target of targets) {
        if (!target.basePath.startsWith('custom/plugins/')) {
            throw new Error(
                `Refusing to write a shim into ${target.basePath}: shims are only generated below custom/plugins ` +
                    '(vendor and platform extensions are checked through host-owned configs instead).',
            );
        }

        for (const sourcePath of target.sourcePaths) {
            const adminFolder = path.dirname(path.resolve(context.projectRoot, sourcePath));
            const shimDir = path.join(adminFolder, SHIM_DIR_NAME);
            const basePreset = path.join(context.administrationRoot, 'extension-tooling', 'tsconfig.base.json');
            const adminTypes = path.join(context.administrationRoot, 'extension-tooling', 'admin-types.d.ts');
            const factoryPath = path.join(context.administrationRoot, 'extension-tooling', 'eslint.mjs');
            const shimTsconfigPath = path.join(shimDir, 'tsconfig.json');
            const shimEslintPath = path.join(shimDir, 'eslint.mjs');
            const shimPaths = buildShimPaths(context, shimDir, adminFolder);

            record(
                context,
                writeManagedFile(path.join(shimDir, '.gitignore'), `# ${GENERATED_MARKER}\n*\n`, context.dryRun),
            );
            record(
                context,
                writeManagedFile(
                    shimTsconfigPath,
                    `// ${GENERATED_MARKER} — machine-specific path into the installed Administration.\n${JSON.stringify(
                        {
                            extends: asRelativeSpecifier(shimTsconfigPath, basePreset),
                            files: [asRelativeSpecifier(shimTsconfigPath, adminTypes)],
                            ...(shimPaths ? { compilerOptions: { paths: shimPaths } } : {}),
                        },
                        null,
                        4,
                    )}\n`,
                    context.dryRun,
                ),
            );
            record(
                context,
                writeManagedFile(
                    shimEslintPath,
                    [
                        `// ${GENERATED_MARKER} — machine-specific path into the installed Administration.`,
                        "import path from 'node:path';",
                        "import { fileURLToPath } from 'node:url';",
                        `import { shopwareAdminExtension } from ${JSON.stringify(asRelativeSpecifier(shimEslintPath, factoryPath))};`,
                        '',
                        'const adminFolder = path.dirname(path.dirname(fileURLToPath(import.meta.url)));',
                        '',
                        `export * from ${JSON.stringify(asRelativeSpecifier(shimEslintPath, factoryPath))};`,
                        '',
                        'export function shopwareAdminExtensionConfig(options = {}) {',
                        '    return shopwareAdminExtension({ tsconfigRootDir: adminFolder, ...options });',
                        '}',
                        '',
                        'export default shopwareAdminExtensionConfig();',
                        '',
                    ].join('\n'),
                    context.dryRun,
                ),
            );

            scaffoldPluginConfigs(context, target.name, adminFolder, sourcePath);
        }
    }
}

/**
 * Scaffolds the plugin's own small, committable tsconfig/eslint that extend the
 * generated `.shopware-admin/` bridge — created only when absent so the developer
 * can see and customize them. An existing config is never overwritten; instead we
 * print the one line to add so it too composes the preset.
 */
function scaffoldPluginConfigs(context: GeneratorContext, name: string, adminFolder: string, sourcePath: string): void {
    const sourceRelative = toPosix(path.relative(adminFolder, path.resolve(context.projectRoot, sourcePath)));
    const include = SOURCE_EXTENSIONS.map((extension) => `${sourceRelative}/**/*.${extension}`);
    const tsconfigPath = path.join(adminFolder, 'tsconfig.json');
    const eslintPath = path.join(adminFolder, 'eslint.config.mjs');
    const tsconfigContent =
        `// Committed config for ${name}. Extends the generated Shopware bridge in .shopware-admin/\n` +
        '// (git-ignored, holds the machine-specific paths). Safe to edit and commit — keep the "extends".\n' +
        '// Spec files stay excluded here — the check type-checks them separately with jest types.\n' +
        `${JSON.stringify(
            {
                extends: './.shopware-admin/tsconfig.json',
                include,
                exclude: SPEC_FILE_SUFFIXES.map((suffix) => `**/*.${suffix}`),
            },
            null,
            4,
        )}\n`;
    const eslintContent = [
        `// Committed config for ${name}. Composes the generated Shopware bridge in .shopware-admin/`,
        '// (git-ignored). Safe to edit and commit — keep the import and the ...spread.',
        "import shopware from './.shopware-admin/eslint.mjs';",
        '',
        'export default [',
        '    ...shopware,',
        '    // Add your own rules here.',
        '];',
        '',
    ].join('\n');

    const tsconfigResult = record(context, writeScaffoldFile(tsconfigPath, tsconfigContent, context.dryRun));
    const eslintResult = record(context, writeScaffoldFile(eslintPath, eslintContent, context.dryRun));

    if (tsconfigResult === 'skipped') {
        context.warnings.push(
            `${tsconfigPath} already exists and was not touched — add \`"extends": "./.shopware-admin/tsconfig.json"\` ` +
                `so ${name} composes the Shopware preset. Own "files" array? Remove it — the bridge provides the ` +
                'type surface. Own paths? Declare them in tsconfig.aliases.json.',
        );
    }

    if (eslintResult === 'skipped') {
        context.warnings.push(
            `${eslintPath} already exists and was not touched — compose the bridge in it: ` +
                "import shopware from './.shopware-admin/eslint.mjs'; export default [ ...shopware /* , your rules */ ];",
        );
    }
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
        writes: [],
        staleFiles: [],
        warnings: [],
        instructions: [],
    };

    const hostModulesPath = path.join(context.toolingRoot, 'host-modules.json');
    const hostModules = (JSON.parse(fs.readFileSync(hostModulesPath, 'utf8')) as { hostModules: Record<string, string> })
        .hostModules;

    for (const [
        moduleName,
        modulePath,
    ] of Object.entries(hostModules)) {
        if (!fs.existsSync(path.join(administrationRoot, modulePath))) {
            context.warnings.push(
                `Host module "${moduleName}" is declared in host-modules.json but ${modulePath} does not exist ` +
                    'in the installed Administration.',
            );
        }
    }

    const entitySchemaAvailable = ensureEntitySchema(context);
    const freshnessWarning = checkDiscoveryFreshness(projectRoot, pluginsConfigPath);

    if (freshnessWarning) {
        context.warnings.push(freshnessWarning);
    }

    let discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);

    if (options.shim) {
        // Write the shim before leaf/root configs, then re-discover so the bridged
        // plugin is already recognized as shim-managed within this same run.
        createShims(context, discovered, options.shim);
        discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);
    }

    // Adopt verified verdicts from earlier check runs where the config inputs
    // are unchanged; prune cache entries of extensions that disappeared.
    const probeCache = readProbeCache(projectRoot);

    if (probeCache) {
        discovered = discovered.map((project) => ({
            ...project,
            ...resolveModesFromCache(project, probeCache, projectRoot, administrationRoot),
        }));

        const knownNames = new Set(discovered.map((project) => project.name));
        const prunedEntries = Object.fromEntries(
            Object.entries(probeCache.entries).filter(([name]) => knownNames.has(name)),
        );

        if (!context.dryRun && Object.keys(prunedEntries).length !== Object.keys(probeCache.entries).length) {
            writeProbeCache(projectRoot, { version: 1, entries: prunedEntries });
        }
    }

    const projects = createLeafConfigs(context, discovered);
    const rootTsconfigState = createRootTsconfig(context, projects);
    const rootEslintState = createRootEslintConfig(context, projects);
    const adminRelative = relativePosix(projectRoot, administrationRoot);
    const ideBootstraps = createIdeBootstraps(context, adminRelative);
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
                'Run `composer admin:generate-entity-schema-types`.',
        );
    }

    const manifest: ExtensionToolingManifest = {
        version: 2,
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
    };
}

const SETUP_COMMAND: CommandSpec = {
    command: 'admin:setup-extension-tooling',
    description: 'Generate TypeScript/ESLint configs and IDE bootstraps for installed Administration extensions.',
    flags: [
        { name: '--check', description: 'Report what would change, write nothing. Exit 1 on drift.' },
        { name: '--explain', description: 'Verbose report: discovered extensions, file list, IDE setup.' },
        { name: '--no-gitignore', description: 'Never manage the ignore block in the project .gitignore.' },
        {
            name: '--shim',
            value: 'required',
            valueName: '<TechnicalName>|all-custom',
            description:
                'Bridge one extension (or "all-custom") with committed configs that extend a generated ' +
                '.shopware-admin/ bridge.',
        },
        { name: '--project-root', value: 'required', valueName: '<path>', description: '', internal: true },
        { name: '--administration-root', value: 'required', valueName: '<path>', description: '', internal: true },
        { name: '--plugins-config', value: 'required', valueName: '<path>', description: '', internal: true },
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

    const checkOnly = parsed.flags.has('--check');
    const explain = parsed.flags.has('--explain');
    const shim = parsed.values['--shim'];

    try {
        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: parsed.values['--plugins-config'],
            shim,
            checkOnly,
            noGitignore: parsed.flags.has('--no-gitignore'),
        });

        console.log(
            renderSetupReport(result, {
                explain,
                checkOnly,
                shim,
                // The plain run is where flags are discovered — and where a flag
                // swallowed by composer (missing "--") would have landed.
                showFlagHint: !checkOnly && !explain && !shim,
            }),
        );

        return checkOnly && result.changed ? 1 : 0;
    } catch (error) {
        console.error(error instanceof Error ? error.message : error);

        return 1;
    }
}

if (require.main === module) {
    process.exitCode = runSetupCli(process.argv.slice(2));
}

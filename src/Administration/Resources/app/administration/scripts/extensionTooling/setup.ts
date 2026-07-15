/**
 * @sw-package framework
 *
 * Generator for the Administration extension tooling ("Connected Toolchain").
 *
 * Pipeline: discover extensions (var/plugins.json) → per-extension leaf
 * tsconfigs in var/admin-extension-tooling/ → root tsconfig/eslint
 * projections → IDE bootstraps → optional per-plugin shims → manifest with
 * freshness hash.
 *
 * Everything written is disposable, marker-owned state. Files a human wrote
 * are never overwritten; collisions are reported with integration
 * instructions instead.
 */

import fs from 'fs';
import path from 'path';
import {
    GENERATED_MARKER,
    SHIM_DIR_NAME,
    STATE_DIR,
    asRelativeSpecifier,
    computeFreshnessHash,
    findExtensionRoot,
    findNearestConfig,
    hasCliFlag,
    isGeneratedContent,
    isWithin,
    readBundleConfig,
    readCliArgument,
    relativePosix,
    writeManagedFile,
    writeStateFile,
} from './shared';
import type {
    ConfigMode,
    ExtensionToolingManifest,
    ExtensionToolingProject,
    ManagedFileState,
    ManifestFileState,
    WriteResult,
} from './shared';

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

export interface SetupExtensionToolingOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    /** Technical name, extension name, or "all-custom": generate committed-config shims. */
    shim?: string;
    /** Validate-only mode: report what would change, write nothing. */
    checkOnly?: boolean;
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
    context.writes.push(result);

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

            return {
                name,
                technicalNames: [...group.technicalNames].sort(),
                basePath: relativePosix(projectRoot, group.extensionRoot),
                sourcePaths: sourcePaths.map((source) => relativePosix(projectRoot, source)),
                vendor: isWithin(group.extensionRoot, vendorRoot),
                tsconfig: tsconfig ? relativePosix(projectRoot, tsconfig) : null,
                eslintConfig: eslintConfig ? relativePosix(projectRoot, eslintConfig) : null,
                tsMode: (tsconfig ? 'custom' : 'managed') as ConfigMode,
                eslintMode: (eslintConfig ? 'custom' : 'managed') as ConfigMode,
                checkTsconfig: '',
            };
        });
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
    const usedFileNames = new Set<string>();

    const configured = projects.map((project) => {
        let fileName = `${safeFileName(project.name)}.json`;
        let suffix = 2;

        while (usedFileNames.has(fileName)) {
            fileName = `${safeFileName(project.name)}-${suffix}.json`;
            suffix += 1;
        }

        usedFileNames.add(fileName);

        const configPath = path.join(projectsDir, fileName);
        const content = `// ${GENERATED_MARKER}\n${JSON.stringify(
            {
                extends: asRelativeSpecifier(configPath, basePreset),
                files: [asRelativeSpecifier(configPath, adminTypes)],
                include: project.sourcePaths.flatMap((sourcePath) =>
                    SOURCE_EXTENSIONS.map(
                        (extension) =>
                            `${asRelativeSpecifier(configPath, path.resolve(context.projectRoot, sourcePath))}/**/*.${extension}`,
                    ),
                ),
            },
            null,
            4,
        )}\n`;

        record(context, writeStateFile(configPath, content, context.dryRun));

        return {
            ...project,
            checkTsconfig: project.tsconfig ?? relativePosix(context.projectRoot, configPath),
        };
    });

    if (fs.existsSync(projectsDir)) {
        for (const existingFile of fs.readdirSync(projectsDir)) {
            if (existingFile.endsWith('.json') && !usedFileNames.has(existingFile)) {
                context.staleFiles.push(path.join(projectsDir, existingFile));

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
        .filter((project) => project.tsMode === 'managed')
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
    const factoryPath = path.join(context.administrationRoot, 'extension-tooling', 'eslint.mjs');
    const factorySpecifier = asRelativeSpecifier(rootEslintPath, factoryPath);
    const extensionRoots = projects.flatMap((project) => project.sourcePaths);
    const content = [
        `// ${GENERATED_MARKER}`,
        `import { shopwareAdminExtension } from '${factorySpecifier}';`,
        '',
        'export default shopwareAdminExtension({',
        '    tsconfigRootDir: import.meta.dirname,',
        '    extensionRoots: [',
        ...extensionRoots.map((extensionRoot) => `        '${extensionRoot}',`),
        '    ],',
        '});',
        '',
    ].join('\n');
    const state = record(context, writeManagedFile(rootEslintPath, content, context.dryRun));

    if (state === 'conflict') {
        context.instructions.push(
            [
                `${rootEslintPath} exists and is not managed by this tool. To integrate, compose the shared factory:`,
                `    import { shopwareAdminExtension } from '${factorySpecifier}';`,
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
            context.writes.push({ file: bootstrap.file, state: 'skipped' });
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

/**
 * TypeScript replaces `paths` wholesale across `extends`, so a plugin that
 * declares its own aliases would erase the preset's host paths. The shim is
 * therefore the single `paths` declarer: it merges the preset's host paths
 * (re-relativized to the shim, machine paths are fine in generated files)
 * with optional plugin aliases from a committed
 * `tsconfig.aliases.json` next to the shim ({ "MyPlugin/*": ["src/*"] },
 * targets relative to the plugin's administration folder).
 */
function buildShimPaths(
    context: GeneratorContext,
    shimDir: string,
    adminFolder: string,
): Record<string, string[]> | null {
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
                        `import { shopwareAdminExtension } from '${asRelativeSpecifier(shimEslintPath, factoryPath)}';`,
                        '',
                        'const adminFolder = path.dirname(path.dirname(fileURLToPath(import.meta.url)));',
                        '',
                        `export * from '${asRelativeSpecifier(shimEslintPath, factoryPath)}';`,
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
        }
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
    const discovered = discoverProjects(projectRoot, administrationRoot, pluginsConfigPath);
    const projects = createLeafConfigs(context, discovered);

    if (options.shim) {
        createShims(context, projects, options.shim);
    }

    const rootTsconfigState = createRootTsconfig(context, projects);
    const rootEslintState = createRootEslintConfig(context, projects);
    const adminRelative = relativePosix(projectRoot, administrationRoot);
    const ideBootstraps = createIdeBootstraps(context, adminRelative);

    if (!entitySchemaAvailable) {
        context.warnings.push(
            'Entity schema types are not available — entity names cannot be type-checked. ' +
                'Run `composer admin:generate-entity-schema-types`.',
        );
    }

    const manifest: ExtensionToolingManifest = {
        version: 1,
        adminRoot: adminRelative,
        entitySchemaAvailable,
        freshnessHash: computeFreshnessHash(
            pluginsConfigPath,
            path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'),
            path.join(administrationRoot, 'package.json'),
        ),
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

function printExplanation(result: SetupExtensionToolingResult): void {
    console.log(`Administration root: ${result.manifest.adminRoot}`);
    console.log(`Entity schema available: ${result.manifest.entitySchemaAvailable ? 'yes' : 'no (stub in place)'}`);
    console.log(`Freshness hash: ${result.manifest.freshnessHash}`);
    console.log(`Root tsconfig.json: ${result.manifest.rootConfigs.tsconfig}`);
    console.log(`Root eslint.config.mjs: ${result.manifest.rootConfigs.eslintConfig}`);

    for (const [
        bootstrap,
        state,
    ] of Object.entries(result.manifest.ideBootstraps)) {
        console.log(`IDE bootstrap ${bootstrap}: ${state}`);
    }

    console.log(`\nDiscovered extensions (${result.manifest.projects.length}):`);

    for (const project of result.manifest.projects) {
        const flags = [
            project.vendor ? 'vendor' : 'writable',
            `ts: ${project.tsMode}${project.tsconfig ? ` (${project.tsconfig})` : ''}`,
            `eslint: ${project.eslintMode}${project.eslintConfig ? ` (${project.eslintConfig})` : ''}`,
        ];

        console.log(`  - ${project.name} [${project.technicalNames.join(', ')}]`);
        console.log(`      ${flags.join(' · ')}`);
        console.log(`      check tsconfig: ${project.checkTsconfig}`);

        for (const sourcePath of project.sourcePaths) {
            console.log(`      source: ${sourcePath}`);
        }
    }
}

function printReport(result: SetupExtensionToolingResult, checkOnly: boolean): void {
    const created = result.writes.filter((write) => write.state === 'created').length;
    const updated = result.writes.filter((write) => write.state === 'updated').length;
    const conflicts = result.writes.filter((write) => write.state === 'conflict');
    const managedCount = result.manifest.projects.filter((project) => project.tsMode === 'managed').length;

    console.log(
        `Administration extension tooling: ${result.manifest.projects.length} extension(s) ` +
            `(${managedCount} zero-config, ${result.manifest.projects.length - managedCount} custom), ` +
            `${created} file(s) created, ${updated} updated.`,
    );

    if (checkOnly && result.changed) {
        console.error('Setup is stale: re-run `composer admin:setup-extension-tooling`.');

        for (const write of result.writes.filter((entry) => entry.state === 'created' || entry.state === 'updated')) {
            console.error(`  would ${write.state === 'created' ? 'create' : 'update'}: ${write.file}`);
        }

        for (const staleFile of result.staleFiles) {
            console.error(`  would delete: ${staleFile}`);
        }
    }

    for (const conflict of conflicts) {
        console.warn(`Conflict: ${conflict.file} is user-owned and was left untouched.`);
    }

    for (const warning of result.warnings) {
        console.warn(warning);
    }

    for (const instruction of result.instructions) {
        console.log(`\n${instruction}`);
    }
}

if (require.main === module) {
    const argv = process.argv.slice(2);
    const administrationRoot = path.resolve(
        readCliArgument(argv, 'administration-root') ?? path.resolve(__dirname, '../..'),
    );
    const projectRoot = readCliArgument(argv, 'project-root') ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        throw new Error('PROJECT_ROOT or --project-root is required.');
    }

    const checkOnly = hasCliFlag(argv, 'check');
    const result = setupExtensionTooling({
        projectRoot,
        administrationRoot,
        pluginsConfigPath: readCliArgument(argv, 'plugins-config'),
        shim: readCliArgument(argv, 'shim'),
        checkOnly,
    });

    if (hasCliFlag(argv, 'explain')) {
        printExplanation(result);
    }

    printReport(result, checkOnly);

    if (checkOnly && result.changed) {
        process.exitCode = 1;
    }
}

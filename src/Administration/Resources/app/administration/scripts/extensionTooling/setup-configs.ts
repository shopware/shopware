/**
 * @sw-package framework
 *
 * Config generators. Tool-owned var/ leaf tsconfigs (one runtime + one spec
 * program per Administration root), the marker-owned root tsconfig/eslint
 * projections, IDE bootstraps, the entity-schema stub gate, and the committed
 * per-extension configs that a `--shim` scaffolds beside its bridge.
 */

import fs from 'fs';
import path from 'path';
import { record } from './setup-context';
import type { GeneratorContext } from './setup-context';
import {
    GENERATED_MARKER,
    STATE_DIR,
    asRelativeSpecifier,
    isGeneratedContent,
    relativePosix,
    toPosix,
    writeManagedFile,
    writeScaffoldFile,
    writeStateFile,
} from './shared';
import type { ExtensionToolingProject, ManagedFileState } from './shared';

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

function safeFileName(name: string): string {
    return name.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
}

export function ensureEntitySchema(context: GeneratorContext): boolean {
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
        `   Generate the real file with: ${context.commands.generateSchema} */`,
        'declare namespace EntitySchema {',
        '    interface Entities {}',
        '}',
        '',
    ].join('\n');

    record(context, writeManagedFile(entitySchemaPath, stubContent, context.dryRun));
    context.warnings.push(
        'The generated entity schema types are missing; a stub with an empty entity list was created. ' +
            `Run \`${context.commands.generateSchema}\` to get installation-specific entity types.`,
    );

    return false;
}

export function createLeafConfigs(
    context: GeneratorContext,
    projects: ExtensionToolingProject[],
): ExtensionToolingProject[] {
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

    const configured = projects.map((project) => ({
        ...project,
        targets: project.targets.map((target) => {
            const targetName =
                project.targets.length === 1
                    ? project.name
                    : `${project.name}-${target.technicalNames[0] ?? path.basename(target.adminFolder)}`;
            let fileName = `${safeFileName(targetName)}.json`;
            let suffix = 2;

            while (usedFileNames.has(fileName)) {
                fileName = `${safeFileName(targetName)}-${suffix}.json`;
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
                    include: sourceGlobs(configPath, target.sourcePath, SOURCE_EXTENSIONS),
                    // Exclude patterns resolve relative to this config, so they
                    // carry the same source prefix as the includes.
                    exclude: sourceGlobs(configPath, target.sourcePath, SPEC_FILE_SUFFIXES),
                },
                null,
                4,
            )}\n`;

            record(context, writeStateFile(configPath, content, context.dryRun));

            // Companion program for spec files: the same type surface plus jest
            // types (spec-types.d.ts), including only this target's specs.
            const specConfigPath = path.join(projectsDir, specFileName);
            const specContent = `// ${GENERATED_MARKER}\n${JSON.stringify(
                {
                    extends: asRelativeSpecifier(specConfigPath, basePreset),
                    // Point typeRoots at the Administration's own @types so the jest
                    // reference in spec-types.d.ts resolves — a triple-slash
                    // reference is looked up relative to this config, not the .d.ts.
                    compilerOptions: {
                        typeRoots: [
                            asRelativeSpecifier(
                                specConfigPath,
                                path.join(context.administrationRoot, 'node_modules', '@types'),
                            ),
                        ],
                    },
                    files: [
                        asRelativeSpecifier(specConfigPath, adminTypes),
                        asRelativeSpecifier(specConfigPath, specTypes),
                    ],
                    include: sourceGlobs(specConfigPath, target.sourcePath, SPEC_FILE_SUFFIXES),
                },
                null,
                4,
            )}\n`;

            record(context, writeStateFile(specConfigPath, specContent, context.dryRun));

            return {
                ...target,
                checkTsconfig: target.tsconfig ?? relativePosix(context.projectRoot, configPath),
                specTsconfig: relativePosix(context.projectRoot, specConfigPath),
            };
        }),
    }));

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

export function createRootTsconfig(context: GeneratorContext, projects: ExtensionToolingProject[]): ManagedFileState {
    const rootTsconfigPath = path.join(context.projectRoot, 'tsconfig.json');
    // Reference both the runtime and spec leaves so the IDE — and ESLint's
    // project service — can associate every managed source and spec file with a
    // program (this is what enables type-aware linting of managed specs).
    const references = projects.flatMap((project) =>
        project.targets
            .filter((target) => target.ts.mode === 'managed')
            .flatMap((target) => [
                { path: `./${target.checkTsconfig}` },
                { path: `./${target.specTsconfig}` },
            ]),
    );
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
                `or remove the file and re-run \`${context.commands.setup}\`.`,
            ].join('\n'),
        );
    }

    return state;
}

export function createRootEslintConfig(context: GeneratorContext, projects: ExtensionToolingProject[]): ManagedFileState {
    const rootEslintPath = path.join(context.projectRoot, 'eslint.config.mjs');
    const extensionRoots = [...new Set(projects.flatMap((project) => project.targets.map((target) => target.sourcePath)))];

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
        '    // Spec files are type-aware-linted here — their leaves are referenced from the root tsconfig.',
        '    typedSpecs: true,',
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

export function createIdeBootstraps(
    context: GeneratorContext,
    adminRelative: string,
    eslintMajorVersion: number,
): Record<string, ManagedFileState> {
    const states: Record<string, ManagedFileState> = {};
    const eslintFlags = eslintMajorVersion < 10 ? ['v10_config_lookup_from_file'] : [];
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
            ...(eslintMajorVersion < 10 ? ['        Extra eslint options: --flag v10_config_lookup_from_file'] : []),
        ].join('\n'),
    );

    return states;
}

/**
 * Scaffolds the plugin's own small, committable tsconfig/eslint that extend the
 * generated `.shopware-admin/` bridge in `configDir` — created only when absent
 * so the developer can see and customize them. In root-config mode one pair
 * includes every source root; per root, one pair covers a single root. An
 * existing config is never overwritten; instead we print the one line to add so
 * it too composes the preset.
 */
export function scaffoldExtensionConfigs(
    context: GeneratorContext,
    name: string,
    configDir: string,
    sourcePaths: string[],
): void {
    const include = sourcePaths.flatMap((sourcePath) => {
        const sourceRelative = toPosix(path.relative(configDir, path.resolve(context.projectRoot, sourcePath)));

        return SOURCE_EXTENSIONS.map((extension) => `${sourceRelative}/**/*.${extension}`);
    });
    const tsconfigPath = path.join(configDir, 'tsconfig.json');
    const eslintPath = path.join(configDir, 'eslint.config.mjs');
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

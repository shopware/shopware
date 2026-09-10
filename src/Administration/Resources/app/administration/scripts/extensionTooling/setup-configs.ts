/**
 * @sw-package framework
 *
 * Config generators: the marker-owned root tsconfig/eslint projections, IDE
 * bootstraps, the entity-schema stub gate, and the per-extension configs
 * (runtime plus a dedicated spec tsconfig) scaffolded beside every generated
 * bridge.
 */

import fs from 'fs';
import path from 'path';
import { record, warn } from './setup-context';
import type { GeneratorContext } from './setup-context';
import { eslintConfigVerdict, tsconfigVerdict } from './probe-static';
import { describeConfigFix } from './report-guidance';
import {
    BRIDGE_ESLINT_SPECIFIER,
    BRIDGE_TSCONFIG_EXTENDS,
    GENERATED_MARKER,
    SHIM_DIR_NAME,
    asRelativeSpecifier,
    isGeneratedContent,
    relativePosix,
    toPosix,
    writeManagedFile,
    writeScaffoldFile,
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
 * that adds jest types. The committed runtime config excludes these suffixes;
 * the generated spec tsconfig (setup-bridge) includes exactly them.
 */
export const SPEC_FILE_SUFFIXES = [
    'spec.ts',
    'spec.tsx',
    'spec.js',
];

/** Collapses a multi-line fix into one warning sentence — the indentation only reads in the report's tree. */
function flattenFix(fix: string[]): string {
    return fix.map((line) => line.trim()).join(' ');
}

/**
 * The settings as a paste-ready JSON fragment: one `"key": value,` per line,
 * indented to sit inside an existing object. The trailing comma is dropped on
 * the last entry, so the block is valid wherever it is pasted — appended to a
 * settings object that already has entries, or as the whole body.
 */
function settingsFragment(settings: Record<string, unknown>): string[] {
    const entries = Object.entries(settings);

    return entries.map(
        (
            [
                name,
                value,
            ],
            index,
        ) => `    "${name}": ${JSON.stringify(value)}${index < entries.length - 1 ? ',' : ''}`,
    );
}

/** Expands dotted setting keys into the nested objects Zed expects. */
function nestKeys(settings: Record<string, unknown>): Record<string, unknown> {
    const nested: Record<string, unknown> = {};

    for (const [
        dottedKey,
        value,
    ] of Object.entries(settings)) {
        const segments = dottedKey.split('.');
        let cursor = nested;

        for (const segment of segments.slice(0, -1)) {
            cursor[segment] = cursor[segment] ?? {};
            cursor = cursor[segment] as Record<string, unknown>;
        }

        cursor[segments[segments.length - 1]] = value;
    }

    return nested;
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
    warn(
        context,
        'The generated entity schema types are missing; a stub with an empty entity list was created. ' +
            `Run \`${context.commands.generateSchema}\` to get installation-specific entity types.`,
    );

    return false;
}

/**
 * The root tsconfig covers every Administration source root that no
 * extension-owned config governs — normally none, because bridging scaffolds a
 * config beside each root. It is the fallback for a root whose bridge could not
 * be written (a read-only vendor directory) and for the dry run, where no
 * bridge exists yet. The sources are included directly instead of through
 * per-target project references, so nothing depends on `composite` builds.
 */
export function createRootTsconfig(context: GeneratorContext, projects: ExtensionToolingProject[]): ManagedFileState {
    const rootTsconfigPath = path.join(context.projectRoot, 'tsconfig.json');
    const uncovered = projects.flatMap((project) => project.targets.filter((target) => target.tsconfig === null));
    // The config sits at the project root, so the project-root-relative source
    // paths are already the right prefix for both globs.
    const globs = (extensions: string[]): string[] =>
        uncovered.flatMap((target) => extensions.map((extension) => `${target.sourcePath}/**/*.${extension}`));
    const projection = {
        extends: asRelativeSpecifier(
            rootTsconfigPath,
            path.join(context.administrationRoot, 'extension-tooling', 'tsconfig.base.json'),
        ),
        files: [
            asRelativeSpecifier(
                rootTsconfigPath,
                path.join(context.administrationRoot, 'extension-tooling', 'admin-types.d.ts'),
            ),
        ],
        include: globs(SOURCE_EXTENSIONS),
        exclude: globs(SPEC_FILE_SUFFIXES),
    };
    const content = `// ${GENERATED_MARKER} — covers extension sources no own config governs.\n${JSON.stringify(
        projection,
        null,
        4,
    )}\n`;
    const state = record(context, writeManagedFile(rootTsconfigPath, content, context.dryRun));

    if (state === 'conflict') {
        context.instructions.push(
            [
                `${relativePosix(context.projectRoot, rootTsconfigPath)} exists and is not managed by this tool. ` +
                    'To integrate, add these includes:',
                ...projection.include.map((glob) => `    "${glob}"`),
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
                `${relativePosix(context.projectRoot, rootEslintPath)} exists and is not managed by this tool. ` +
                    'To integrate, compose the shared factory:',
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
    const tsdk = `${adminRelative}/node_modules/typescript/lib`;
    const nodePath = `${adminRelative}/node_modules`;
    const flags = eslintMajorVersion < 10 ? ['v10_config_lookup_from_file'] : [];
    // One settings map per IDE is the single source for both the generated file
    // and the instructions printed when the file is user-owned — they cannot
    // drift apart. VS Code takes the dotted keys literally, Zed nests them.
    const bootstraps: Array<{ key: string; nested: boolean; settings: Record<string, unknown> }> = [
        {
            key: '.vscode/settings.json',
            nested: false,
            settings: {
                // `typescript.tsdk` is deprecated in favour of this key.
                'js/ts.tsdk.path': tsdk,
                'eslint.nodePath': nodePath,
                ...(flags.length > 0 ? { 'eslint.options': { flags } } : {}),
                'eslint.validate': [
                    'javascript',
                    'typescript',
                    'vue',
                    'twig',
                ],
                'files.associations': { '*.html.twig': 'twig' },
            },
        },
        {
            key: '.zed/settings.json',
            nested: true,
            settings: {
                'lsp.vtsls.initialization_options.typescript.tsdk': tsdk,
                'lsp.eslint.settings.nodePath': nodePath,
                ...(flags.length > 0 ? { 'lsp.eslint.settings.options.flags': flags } : {}),
            },
        },
    ];

    for (const { key, nested, settings } of bootstraps) {
        const file = path.join(context.projectRoot, ...key.split('/'));

        if (fs.existsSync(file) && !isGeneratedContent(fs.readFileSync(file, 'utf8'))) {
            states[key] = 'skipped';
            record(context, { file, state: 'skipped' });
            context.instructions.push(
                [
                    // The project-relative `key`, not the absolute `file`: a path
                    // printed from inside a container is only clickable in the
                    // editor when it resolves against the workspace root.
                    `${key} is user-owned and was not touched. For IDE support add:`,
                    // Comma-separated and in the shape the file itself uses, so
                    // the block pastes straight into the existing object.
                    ...settingsFragment(nested ? nestKeys(settings) : settings),
                ].join('\n'),
            );

            continue;
        }

        const content = `// ${GENERATED_MARKER}\n${JSON.stringify(nested ? nestKeys(settings) : settings, null, 4)}\n`;

        states[key] = record(context, writeManagedFile(file, content, context.dryRun));
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
 * generated bridge in `configDir` — created only when absent so the developer
 * can see and customize them. In root-config mode one pair includes every
 * source root; per root, one pair covers a single root. An existing config is
 * never overwritten; instead we print the one line to add so it too composes
 * the preset.
 */
export function scaffoldExtensionConfigs(
    context: GeneratorContext,
    name: string,
    configDir: string,
    sourcePaths: string[],
    vendor = false,
): void {
    const include = sourcePaths.flatMap((sourcePath) => {
        const sourceRelative = toPosix(path.relative(configDir, path.resolve(context.projectRoot, sourcePath)));

        return SOURCE_EXTENSIONS.map((extension) => `${sourceRelative}/**/*.${extension}`);
    });
    const tsconfigPath = path.join(configDir, 'tsconfig.json');
    const eslintPath = path.join(configDir, 'eslint.config.mjs');
    // A vendor extension's files are composer-managed: "commit" makes no sense
    // there, re-running setup after an update is the restore path instead.
    const configKind = vendor ? 'Local config' : 'Committed config';
    const tsconfigLifecycleNote = vendor
        ? 'A composer update removes this file; re-running setup restores it.'
        : 'Safe to edit and commit — keep the "extends".';
    const eslintLifecycleNote = vendor
        ? 'A composer update removes this file; re-running setup restores it.'
        : 'Safe to edit and commit — keep the import and the ...spread.';
    const tsconfigContent =
        `// ${configKind} for ${name}. Extends the generated Shopware bridge in ${SHIM_DIR_NAME}/\n` +
        `// (git-ignored, holds the machine-specific paths). ${tsconfigLifecycleNote}\n` +
        '// Spec files stay excluded here — the check type-checks them separately with jest types.\n' +
        `${JSON.stringify(
            {
                extends: BRIDGE_TSCONFIG_EXTENDS,
                include,
                exclude: SPEC_FILE_SUFFIXES.map((suffix) => `**/*.${suffix}`),
            },
            null,
            4,
        )}\n`;
    const eslintContent = [
        `// ${configKind} for ${name}. Composes the generated Shopware bridge in ${SHIM_DIR_NAME}/`,
        `// (git-ignored). ${eslintLifecycleNote}`,
        `import shopware from '${BRIDGE_ESLINT_SPECIFIER}';`,
        '',
        'export default [',
        '    ...shopware,',
        '    // Add your own rules here.',
        '];',
        '',
    ].join('\n');

    const tsconfigResult = record(context, writeScaffoldFile(tsconfigPath, tsconfigContent, context.dryRun));
    const eslintResult = record(context, writeScaffoldFile(eslintPath, eslintContent, context.dryRun));

    // A pre-existing config is only worth warning about when it does not
    // actually compose the preset — warning on mere existence nags a correctly
    // wired extension at every run. The static verdict is safe here: it matches
    // the bridge on the resolved path before reading it, so it is right even on
    // the first run, where the bridge is not on disk yet (or never will be,
    // under --check).
    if (tsconfigResult === 'skipped') {
        const verdict = tsconfigVerdict(tsconfigPath, relativePosix(context.projectRoot, tsconfigPath));

        if (!verdict.composes) {
            warn(
                context,
                `${tsconfigPath} already exists and was not touched — ${verdict.detail ?? 'it does not compose the Shopware preset.'} ` +
                    `Fix: ${flattenFix(describeConfigFix('TypeScript', verdict))}`,
                name,
            );
        }
    }

    if (eslintResult === 'skipped') {
        const verdict = eslintConfigVerdict(eslintPath, relativePosix(context.projectRoot, eslintPath));

        if (!verdict.composes) {
            warn(
                context,
                `${eslintPath} already exists and was not touched — ${verdict.detail ?? 'it does not compose the Shopware factory.'} ` +
                    `Fix: ${flattenFix(describeConfigFix('ESLint', verdict))}`,
                name,
            );
        }
    }
}

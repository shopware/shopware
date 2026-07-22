/**
 * @sw-package framework
 *
 * Shim/bridge generation for `--shim`ed extensions. A bridge is the
 * git-ignored, machine-specific hop into the installed Administration; the
 * committed extension configs (scaffolded in setup-configs) extend it. Decides
 * whether an extension is bridged once per Administration root or once beside a
 * package-level config that governs several roots (`resolveBridgePlan`), then
 * writes the bridge files and merges plugin aliases into its `paths`.
 */

import fs from 'fs';
import path from 'path';
import { record } from './setup-context';
import type { GeneratorContext } from './setup-context';
import { scaffoldExtensionConfigs } from './setup-configs';
import { GENERATED_MARKER, SHIM_DIR_NAME, asRelativeSpecifier, relativePosix, writeManagedFile } from './shared';
import type { AdministrationTarget, ExtensionToolingProject } from './shared';

/**
 * Aliases contributing to a bridge's merged `paths`. Each source's targets
 * resolve relative to its own `baseDir`, so a root-config bridge can carry the
 * aliases declared beside every covered Administration root, not only one.
 */
interface AliasSource {
    aliasesPath: string;
    baseDir: string;
}

function dedupeAliasSources(sources: AliasSource[]): AliasSource[] {
    const seen = new Set<string>();

    return sources.filter((source) => {
        if (seen.has(source.aliasesPath)) {
            return false;
        }

        seen.add(source.aliasesPath);

        return true;
    });
}

/**
 * TypeScript replaces `paths` wholesale across `extends`, so a plugin that
 * declares its own aliases would erase the preset's host paths. The bridge is
 * therefore the single `paths` declarer: it merges the preset's host paths
 * (re-relativized to the bridge, machine paths are fine in generated files)
 * with plugin aliases from every `tsconfig.aliases.json` beside a covered root
 * ({ "MyPlugin/*": ["src/*"] }, targets relative to that alias file's directory).
 */
function buildShimPaths(
    context: GeneratorContext,
    shimDir: string,
    aliasSources: AliasSource[],
): Record<string, string[]> | null {
    const presentSources = aliasSources.filter((source) => fs.existsSync(source.aliasesPath));

    if (presentSources.length === 0) {
        return null;
    }

    const shimTsconfigPath = path.join(shimDir, 'tsconfig.json');
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
            asRelativeSpecifier(shimTsconfigPath, path.resolve(presetDir, target)),
        );
    }

    for (const source of presentSources) {
        const aliases = JSON.parse(fs.readFileSync(source.aliasesPath, 'utf8')) as Record<string, string[] | string>;

        for (const [
            alias,
            targets,
        ] of Object.entries(aliases)) {
            mergedPaths[alias] = (Array.isArray(targets) ? targets : [targets]).map((target) =>
                asRelativeSpecifier(shimTsconfigPath, path.resolve(source.baseDir, target)),
            );
        }
    }

    return mergedPaths;
}

/**
 * Writes the three git-ignored bridge files into `<bridgeParent>/.shopware-admin/`.
 * The bridge is the machine-specific hop into the installed Administration; the
 * eslint bridge derives its own parent directory at runtime, so one bridge serves
 * whichever config — per-root or a shared package root — sits beside it.
 */
function writeBridge(context: GeneratorContext, bridgeParent: string, aliasSources: AliasSource[]): void {
    const shimDir = path.join(bridgeParent, SHIM_DIR_NAME);
    const basePreset = path.join(context.administrationRoot, 'extension-tooling', 'tsconfig.base.json');
    const adminTypes = path.join(context.administrationRoot, 'extension-tooling', 'admin-types.d.ts');
    const factoryPath = path.join(context.administrationRoot, 'extension-tooling', 'eslint.mjs');
    const shimTsconfigPath = path.join(shimDir, 'tsconfig.json');
    const shimEslintPath = path.join(shimDir, 'eslint.mjs');
    const shimPaths = buildShimPaths(context, shimDir, aliasSources);

    record(context, writeManagedFile(path.join(shimDir, '.gitignore'), `# ${GENERATED_MARKER}\n*\n`, context.dryRun));
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
}

type BridgePlan = { kind: 'per-root' } | { kind: 'root-config'; dir: string } | { kind: 'ambiguous'; candidates: string[] };

/**
 * Decides whether an extension is bridged once beside a package-level config
 * that already governs several Administration roots, or once per root. An
 * explicit `--root-config` always wins. Otherwise a single owned config shared
 * by two or more roots is adopted as the root config; two or more competing
 * shared configs are ambiguous and require the explicit flag; anything else (a
 * single root, genuinely independent per-root configs, or zero-config) stays
 * per-root, so independent layouts keep their existing behavior.
 */
function resolveBridgePlan(
    context: GeneratorContext,
    project: ExtensionToolingProject,
    explicitRootConfig?: string,
): BridgePlan {
    const extensionRoot = path.resolve(context.projectRoot, project.basePath);

    if (explicitRootConfig !== undefined) {
        return { kind: 'root-config', dir: path.resolve(extensionRoot, explicitRootConfig) };
    }

    if (project.targets.length < 2) {
        return { kind: 'per-root' };
    }

    const sharedConfigDirs = (configOf: (target: AdministrationTarget) => string | null): string[] => {
        const counts = new Map<string, number>();

        for (const target of project.targets) {
            const config = configOf(target);

            if (config) {
                const dir = path.dirname(path.resolve(context.projectRoot, config));

                counts.set(dir, (counts.get(dir) ?? 0) + 1);
            }
        }

        return [...counts.entries()]
            .filter(
                ([
                    ,
                    count,
                ]) => count >= 2,
            )
            .map(([dir]) => dir);
    };
    const candidates = [
        ...new Set([
            ...sharedConfigDirs((target) => target.tsconfig),
            ...sharedConfigDirs((target) => target.eslintConfig),
        ]),
    ];

    if (candidates.length === 0) {
        return { kind: 'per-root' };
    }

    if (candidates.length === 1) {
        return { kind: 'root-config', dir: candidates[0] };
    }

    return { kind: 'ambiguous', candidates };
}

export function createShims(
    context: GeneratorContext,
    projects: ExtensionToolingProject[],
    shim: string,
    rootConfig?: string,
): void {
    const selected =
        shim === 'all-custom'
            ? projects.filter((project) => project.basePath.startsWith('custom/plugins/'))
            : projects.filter((project) => project.name === shim || project.technicalNames.includes(shim));

    if (selected.length === 0) {
        const available = projects.map((project) => project.name).join(', ');

        throw new Error(`No extension matches --shim=${shim}. Discovered extensions: ${available || '(none)'}.`);
    }

    if (rootConfig !== undefined && shim === 'all-custom') {
        throw new Error('--root-config cannot be combined with --shim=all-custom; bridge one extension at a time.');
    }

    for (const project of selected) {
        if (!project.basePath.startsWith('custom/plugins/')) {
            throw new Error(
                `Refusing to write a shim into ${project.basePath}: shims are only generated below custom/plugins ` +
                    '(vendor and platform extensions are checked through host-owned configs instead).',
            );
        }

        const plan = resolveBridgePlan(context, project, rootConfig);

        if (plan.kind === 'ambiguous') {
            const relative = plan.candidates
                .map((dir) => relativePosix(context.projectRoot, dir))
                .sort()
                .join(', ');

            throw new Error(
                `${project.name} has more than one package-level config governing multiple Administration roots ` +
                    `(${relative}). Choose one with --root-config=<dir> relative to ${project.basePath}, ` +
                    'or give each root its own independent config.',
            );
        }

        if (plan.kind === 'root-config') {
            const coveredAdminFolders = project.targets.map((target) =>
                path.resolve(context.projectRoot, target.adminFolder),
            );
            const aliasSources = dedupeAliasSources([
                { aliasesPath: path.join(plan.dir, 'tsconfig.aliases.json'), baseDir: plan.dir },
                ...coveredAdminFolders.map((folder) => ({
                    aliasesPath: path.join(folder, 'tsconfig.aliases.json'),
                    baseDir: folder,
                })),
            ]);

            writeBridge(context, plan.dir, aliasSources);
            scaffoldExtensionConfigs(
                context,
                project.name,
                plan.dir,
                project.targets.map((target) => target.sourcePath),
            );

            continue;
        }

        for (const target of project.targets) {
            const adminFolder = path.resolve(context.projectRoot, target.adminFolder);

            writeBridge(context, adminFolder, [
                { aliasesPath: path.join(adminFolder, 'tsconfig.aliases.json'), baseDir: adminFolder },
            ]);
            scaffoldExtensionConfigs(context, project.name, adminFolder, [target.sourcePath]);
        }
    }
}

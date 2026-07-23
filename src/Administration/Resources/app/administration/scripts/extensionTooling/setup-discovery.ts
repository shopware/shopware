/**
 * @sw-package framework
 *
 * Discovery: read the installed extensions from var/plugins.json, group their
 * Administration source roots by extension, and resolve each root's nearest
 * owned tsconfig/eslint config plus its statically-derived TS/ESLint mode.
 * Includes the var/plugins.json freshness heuristic.
 */

import fs from 'fs';
import path from 'path';
import {
    SHIM_DIR_NAME,
    canonicalizePath,
    findExtensionRoot,
    findNearestConfig,
    isWithin,
    readBundleConfig,
    relativePosix,
} from './shared';
import type { AdministrationTarget, ExtensionToolingProject } from './shared';
import { resolveStaticEslintMode, resolveStaticTsMode } from './probe';

const ESLINT_CONFIG_NAMES = [
    'eslint.config.mjs',
    'eslint.config.js',
    'eslint.config.cjs',
    'eslint.config.ts',
];

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
        targets: Map<string, { sourcePath: string; technicalNames: Set<string> }>;
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

        // var/plugins.json is only semi-trusted (it mirrors installed plugin
        // composer data). An absolute or `../`-traversing basePath must not let
        // the checker read, type-check, lint, or print files outside the
        // project — clamp discovery to the project root. Canonicalize both
        // sides so a symlinked project root (e.g. macOS /var → /private/var)
        // does not reject legitimate in-project targets.
        if (
            !fs.existsSync(sourcePath) ||
            sourcePath === administrationSourcePath ||
            !isWithin(canonicalizePath(sourcePath), canonicalizePath(projectRoot))
        ) {
            continue;
        }

        const extensionRoot = findExtensionRoot(projectRoot, bundleBasePath);
        const group = groups.get(extensionRoot) ?? {
            extensionRoot,
            technicalNames: new Set<string>(),
            targets: new Map<string, { sourcePath: string; technicalNames: Set<string> }>(),
        };

        group.technicalNames.add(bundle.technicalName);
        const canonicalSourcePath = canonicalizePath(sourcePath);
        const target = group.targets.get(canonicalSourcePath) ?? {
            // Keep paths in the caller's project-root namespace for portable
            // manifest entries; use the canonical form only as the dedupe key.
            sourcePath: path.normalize(sourcePath),
            technicalNames: new Set<string>(),
        };

        target.technicalNames.add(bundle.technicalName);
        group.targets.set(canonicalSourcePath, target);
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
            const targets: AdministrationTarget[] = [...group.targets.values()]
                .sort((left, right) => left.sourcePath.localeCompare(right.sourcePath))
                .map((target) => {
                    const tsconfig = findNearestConfig(target.sourcePath, group.extensionRoot, ['tsconfig.json']);
                    const eslintConfig = findNearestConfig(target.sourcePath, group.extensionRoot, ESLINT_CONFIG_NAMES);
                    // A bridge sits beside the config it serves: the source root
                    // itself (per-root shim) or the directory of the owned config
                    // that extends it (root-config shim). Check every candidate so
                    // a root-config bridge is not mistaken for "no bridge".
                    const bridgeDirs = new Set<string>([path.dirname(target.sourcePath)]);

                    if (tsconfig) {
                        bridgeDirs.add(path.dirname(tsconfig));
                    }

                    if (eslintConfig) {
                        bridgeDirs.add(path.dirname(eslintConfig));
                    }

                    return {
                        technicalNames: [...target.technicalNames].sort(),
                        sourcePath: relativePosix(projectRoot, target.sourcePath),
                        adminFolder: relativePosix(projectRoot, path.dirname(target.sourcePath)),
                        bridgePresent: [...bridgeDirs].some((dir) =>
                            fs.existsSync(path.join(dir, SHIM_DIR_NAME, 'tsconfig.json')),
                        ),
                        tsconfig: tsconfig ? relativePosix(projectRoot, tsconfig) : null,
                        eslintConfig: eslintConfig ? relativePosix(projectRoot, eslintConfig) : null,
                        ts: resolveStaticTsMode(tsconfig),
                        eslint: resolveStaticEslintMode(eslintConfig),
                        checkTsconfig: '',
                        specTsconfig: '',
                    };
                });

            return {
                name,
                technicalNames: [...group.technicalNames].sort(),
                basePath: relativePosix(projectRoot, group.extensionRoot),
                vendor: isWithin(group.extensionRoot, vendorRoot),
                targets,
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

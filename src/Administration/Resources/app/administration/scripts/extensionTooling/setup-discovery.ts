/**
 * @sw-package framework
 *
 * Discovery: read the installed extensions from var/plugins.json, group their
 * Administration source roots by extension, and resolve each root's nearest
 * owned tsconfig/eslint config plus its statically-derived TS/ESLint mode.
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
import { eslintConfigVerdict, tsconfigVerdict } from './probe-static';

const ESLINT_CONFIG_NAMES = [
    'eslint.config.mjs',
    'eslint.config.js',
    'eslint.config.cjs',
    'eslint.config.ts',
];

export function discoverProjects(
    projectRoot: string,
    administrationRoot: string,
    bundleDumpPath: string,
): ExtensionToolingProject[] {
    const bundles = readBundleConfig(bundleDumpPath);
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
                    // itself (per-root bridge) or the directory of the owned config
                    // that extends it (root-config bridge). Check every candidate so
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
                        tsconfig: tsconfig ? tsconfigVerdict(tsconfig, relativePosix(projectRoot, tsconfig)) : null,
                        eslintConfig: eslintConfig
                            ? eslintConfigVerdict(eslintConfig, relativePosix(projectRoot, eslintConfig))
                            : null,
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

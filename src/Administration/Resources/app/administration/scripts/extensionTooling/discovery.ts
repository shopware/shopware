/**
 * @sw-package framework
 *
 * Discovery: read the installed bundles from `var/plugins.json` and turn every
 * one that ships Administration sources into a checkable root.
 */

import fs from 'fs';
import path from 'path';
import { canonicalizePath, findPackageRoot, isPlatformPackage, isWithin, readBundleConfig, slugify } from './shared';
import type { AdminRoot } from './shared';

export interface DiscoveryOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath: string;
}

export interface RootSelection {
    selected: AdminRoot[];
    /** Names passed on the command line that matched no discovered root. */
    unknownNames: string[];
    /** Platform roots left out because `--include-platform` was not passed. */
    skippedPlatform: AdminRoot[];
}

export function discoverAdminRoots(options: DiscoveryOptions): AdminRoot[] {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationSourcePath = path.resolve(options.administrationRoot, 'src');
    const bundles = readBundleConfig(options.pluginsConfigPath);
    const canonicalProjectRoot = canonicalizePath(projectRoot);

    const roots: AdminRoot[] = [];
    const seenSourcePaths = new Set<string>();
    const usedSlugs = new Set<string>();

    for (const bundle of bundles) {
        if (bundle.administrationPath === null) {
            continue;
        }

        const basePath = path.isAbsolute(bundle.basePath)
            ? path.normalize(bundle.basePath)
            : path.resolve(projectRoot, bundle.basePath);
        const sourcePath = path.resolve(basePath, bundle.administrationPath);

        // var/plugins.json mirrors installed plugin metadata and is therefore only
        // semi-trusted: an absolute or `../`-traversing basePath must not make the
        // checker read, type-check, lint or print files outside the installation.
        if (!isWithin(canonicalizePath(sourcePath), canonicalProjectRoot)) {
            continue;
        }

        // Core bundles declare an Administration path they do not ship, and the
        // Administration itself is the host, not an extension.
        if (!fs.existsSync(sourcePath) || sourcePath === administrationSourcePath) {
            continue;
        }

        const canonicalSourcePath = canonicalizePath(sourcePath);

        if (seenSourcePaths.has(canonicalSourcePath)) {
            continue;
        }

        seenSourcePaths.add(canonicalSourcePath);

        const packageRoot = findPackageRoot(basePath, projectRoot);
        const extensionRoot = packageRoot ?? basePath;
        let slug = slugify(bundle.technicalName);

        for (let suffix = 2; usedSlugs.has(slug); suffix += 1) {
            slug = `${slugify(bundle.technicalName)}-${suffix}`;
        }

        usedSlugs.add(slug);

        roots.push({
            bundleName: bundle.name,
            technicalName: bundle.technicalName,
            extensionName: path.basename(extensionRoot),
            extensionRoot,
            sourcePath,
            adminFolder: path.dirname(sourcePath),
            slug,
            platform: isPlatformPackage(packageRoot),
        });
    }

    return roots.sort((left, right) => left.sourcePath.localeCompare(right.sourcePath));
}

function matchesName(root: AdminRoot, name: string): boolean {
    const needle = name.toLowerCase();

    return (
        root.extensionName.toLowerCase() === needle ||
        root.bundleName.toLowerCase() === needle ||
        root.technicalName.toLowerCase() === needle ||
        root.slug === needle
    );
}

/**
 * Applies the positional name filter and the platform filter.
 *
 * An explicitly named root is always checked, even when it is platform code:
 * refusing a name that plainly exists is worse than silently widening the
 * default set, and `--include-platform` only governs the unnamed default.
 */
export function selectRoots(roots: AdminRoot[], options: { names: string[]; includePlatform: boolean }): RootSelection {
    if (options.names.length > 0) {
        const selected = roots.filter((root) => options.names.some((name) => matchesName(root, name)));
        const unknownNames = options.names.filter((name) => !roots.some((root) => matchesName(root, name)));

        return { selected, unknownNames, skippedPlatform: [] };
    }

    if (options.includePlatform) {
        return { selected: roots, unknownNames: [], skippedPlatform: [] };
    }

    return {
        selected: roots.filter((root) => !root.platform),
        unknownNames: [],
        skippedPlatform: roots.filter((root) => root.platform),
    };
}

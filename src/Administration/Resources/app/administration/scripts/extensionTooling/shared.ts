/**
 * @sw-package framework
 *
 * Shared types and path helpers for the Administration extension checker.
 */

import fs from 'fs';
import path from 'path';

/** Directory below `var/` that holds the generated, ephemeral programs. */
export const TOOLING_DIR = 'admin-extension-tooling';

/** Directory inside the Administration that ships the shared presets. */
export const PRESET_DIR = 'extension-tooling';

/** Composer package types that mark a package as an extension rather than platform. */
const EXTENSION_PACKAGE_TYPES = [
    'shopware-platform-plugin',
    'shopware-app',
];

export interface BundleConfig {
    /** Key in `var/plugins.json`, e.g. `SwagCommercialSubscription`. */
    name: string;
    basePath: string;
    technicalName: string;
    /** Administration source path relative to `basePath`, `null` when the bundle has none. */
    administrationPath: string | null;
}

export interface AdminRoot {
    bundleName: string;
    technicalName: string;
    /** Directory name of the Composer package the bundle belongs to, e.g. `SwagCommercial`. */
    extensionName: string;
    /** Absolute path of the Composer package root. */
    extensionRoot: string;
    /** Absolute path of the Administration sources, e.g. `<…>/Resources/app/administration/src`. */
    sourcePath: string;
    /** Absolute path of the Administration app folder, i.e. the parent of `sourcePath`. */
    adminFolder: string;
    /** Unique directory name below `var/admin-extension-tooling`. */
    slug: string;
    platform: boolean;
}

export type ToolName = 'types' | 'lint';

export type Severity = 'error' | 'warning';

export interface Finding {
    /** Path relative to the project root, POSIX separators. */
    file: string | null;
    line: number | null;
    column: number | null;
    severity: Severity;
    /** `TS2322` for the type checker, the rule id for ESLint. */
    rule: string | null;
    message: string;
}

export interface ToolRun {
    tool: ToolName;
    filesChecked: number;
    findings: Finding[];
    /**
     * Diagnostics the tool reported for files outside the checked root — the
     * Administration's own sources enter every extension program through
     * `admin-types.d.ts`. They are counted rather than listed: an extension
     * author cannot act on them, so they must not decide the exit code.
     */
    externalFindings: number;
    /** Tool-level failures: a missing binary, an unparseable report, a crash. */
    errors: string[];
}

export interface RootReport {
    root: AdminRoot;
    runs: ToolRun[];
}

export interface CheckReport {
    roots: RootReport[];
    /** Failures that are not attributable to a single root. */
    errors: string[];
}

/**
 * Strips comments and trailing commas so a plugin-authored `tsconfig.json` —
 * JSONC in practice, and commented in most real plugins — can be read at all.
 * String literals are tracked so a `//` inside a path never starts a comment.
 */
export function parseJsonc<T>(source: string): T {
    let result = '';
    let inString = false;
    let inLineComment = false;
    let inBlockComment = false;
    let escaped = false;

    for (let index = 0; index < source.length; index += 1) {
        const character = source[index];
        const next = source[index + 1];

        if (inLineComment) {
            if (character === '\n') {
                inLineComment = false;
                result += character;
            }

            continue;
        }

        if (inBlockComment) {
            if (character === '*' && next === '/') {
                inBlockComment = false;
                index += 1;
            }

            continue;
        }

        if (inString) {
            result += character;

            if (escaped) {
                escaped = false;
            } else if (character === '\\') {
                escaped = true;
            } else if (character === '"') {
                inString = false;
            }

            continue;
        }

        if (character === '"') {
            inString = true;
            result += character;

            continue;
        }

        if (character === '/' && next === '/') {
            inLineComment = true;
            index += 1;

            continue;
        }

        if (character === '/' && next === '*') {
            inBlockComment = true;
            index += 1;

            continue;
        }

        result += character;
    }

    return JSON.parse(result.replace(/,(\s*[}\]])/g, '$1')) as T;
}

export function readJsoncFile<T>(filePath: string): T {
    return parseJsonc<T>(fs.readFileSync(filePath, 'utf8'));
}

/**
 * Reads the bundle configuration dumped by `bin/console bundle:dump`.
 *
 * @throws when the file is missing or not a bundle map — the caller turns that
 *   into a tool error with the `bundle:dump` hint.
 */
export function readBundleConfig(pluginsConfigPath: string): BundleConfig[] {
    if (!fs.existsSync(pluginsConfigPath)) {
        throw new Error(`No bundle configuration found at ${pluginsConfigPath}. Run "bin/console bundle:dump" first.`);
    }

    const raw =
        readJsoncFile<
            Record<string, { basePath?: string; technicalName?: string; administration?: { path?: string } | null }>
        >(pluginsConfigPath);

    if (typeof raw !== 'object' || raw === null || Array.isArray(raw)) {
        throw new Error(`${pluginsConfigPath} is not a bundle configuration. Run "bin/console bundle:dump" first.`);
    }

    return Object.entries(raw)
        .filter(
            ([
                ,
                bundle,
            ]) => typeof bundle?.basePath === 'string',
        )
        .map(
            ([
                name,
                bundle,
            ]) => ({
                name,
                basePath: bundle.basePath as string,
                technicalName: typeof bundle.technicalName === 'string' ? bundle.technicalName : name.toLowerCase(),
                administrationPath: typeof bundle.administration?.path === 'string' ? bundle.administration.path : null,
            }),
        );
}

export function toPosix(filePath: string): string {
    return filePath.split(path.sep).join('/');
}

export function relativePosix(from: string, to: string): string {
    return toPosix(path.relative(from, to));
}

/**
 * Resolves symlinks where possible so a symlinked project root (macOS
 * `/var` → `/private/var`) does not make in-project paths look external.
 */
export function canonicalizePath(filePath: string): string {
    try {
        return fs.realpathSync.native(filePath);
    } catch {
        return path.resolve(filePath);
    }
}

export function isWithin(candidate: string, directory: string): boolean {
    const relative = path.relative(directory, candidate);

    return relative === '' || (!relative.startsWith('..') && !path.isAbsolute(relative));
}

/**
 * Nearest directory at or above `startDir` that owns a `composer.json`, without
 * ever reaching the project root: the root manifest describes the installation,
 * not an extension, and treating it as one would make every extension without
 * its own manifest look like platform code.
 */
export function findPackageRoot(startDir: string, projectRoot: string): string | null {
    let current = path.resolve(startDir);

    while (isWithin(current, projectRoot) && current !== path.resolve(projectRoot)) {
        if (fs.existsSync(path.join(current, 'composer.json'))) {
            return current;
        }

        const parent = path.dirname(current);

        if (parent === current) {
            return null;
        }

        current = parent;
    }

    return null;
}

/**
 * Whether a Composer package belongs to the Shopware platform itself.
 *
 * The `shopware/` vendor prefix is the precise signal — it holds in the
 * monorepo (`src/Core` → `shopware/core`) and in a Composer install
 * (`vendor/shopware/administration`) alike, where a `custom/plugins/` path
 * prefix does not and mislabels `custom/static-plugins/*`. The package type
 * decides first, though, because Shopware ships first-party *extensions* under
 * the same vendor prefix: `custom/plugins/SwagCommercial` is
 * `shopware/commercial` and must be checked like any other plugin.
 */
export function isPlatformPackage(packageRoot: string | null): boolean {
    if (packageRoot === null) {
        return false;
    }

    const manifestPath = path.join(packageRoot, 'composer.json');

    if (!fs.existsSync(manifestPath)) {
        return false;
    }

    let manifest: { name?: unknown; type?: unknown };

    try {
        manifest = readJsoncFile<{ name?: unknown; type?: unknown }>(manifestPath);
    } catch {
        return false;
    }

    if (typeof manifest.type === 'string' && EXTENSION_PACKAGE_TYPES.includes(manifest.type)) {
        return false;
    }

    return typeof manifest.name === 'string' && manifest.name.startsWith('shopware/');
}

export function slugify(value: string): string {
    const slug = value
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return slug === '' ? 'extension' : slug;
}

export function findNearestTsconfig(startDir: string, stopDir: string): string | null {
    let current = path.resolve(startDir);
    const boundary = path.resolve(stopDir);

    while (isWithin(current, boundary)) {
        const candidate = path.join(current, 'tsconfig.json');

        if (fs.existsSync(candidate)) {
            return candidate;
        }

        if (current === boundary) {
            return null;
        }

        const parent = path.dirname(current);

        if (parent === current) {
            return null;
        }

        current = parent;
    }

    return null;
}

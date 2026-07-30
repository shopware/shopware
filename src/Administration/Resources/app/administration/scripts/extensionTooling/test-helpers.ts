/**
 * @sw-package framework
 *
 * Fixture helpers for the extension-checker specs.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';

export const REAL_ADMINISTRATION_ROOT = path.resolve(__dirname, '..', '..');

export interface BundleFixture {
    basePath: string;
    technicalName?: string;
    administrationPath?: string | null;
}

export function createTempProject(): string {
    return fs.realpathSync.native(fs.mkdtempSync(path.join(os.tmpdir(), 'sw-extension-check-')));
}

export function removeTempProject(projectRoot: string): void {
    fs.rmSync(projectRoot, { recursive: true, force: true });
}

export function writeTree(root: string, tree: Record<string, string>): void {
    for (const [
        relativePath,
        content,
    ] of Object.entries(tree)) {
        const target = path.join(root, relativePath);

        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.writeFileSync(target, content, 'utf8');
    }
}

export function writeBundleConfig(projectRoot: string, bundles: Record<string, BundleFixture>): string {
    const config = Object.fromEntries(
        Object.entries(bundles).map(
            ([
                name,
                bundle,
            ]) => [
                name,
                {
                    basePath: bundle.basePath,
                    technicalName: bundle.technicalName ?? name.toLowerCase(),
                    administration:
                        bundle.administrationPath === null
                            ? null
                            : { path: bundle.administrationPath ?? 'Resources/app/administration/src' },
                },
            ],
        ),
    );
    const configPath = path.join(projectRoot, 'var', 'plugins.json');

    fs.mkdirSync(path.dirname(configPath), { recursive: true });
    fs.writeFileSync(configPath, JSON.stringify(config, null, 4), 'utf8');

    return configPath;
}

export function composerManifest(name: string, type: string): string {
    return `${JSON.stringify({ name, type }, null, 4)}\n`;
}

/**
 * A minimal Administration root that carries the real presets and the real
 * binaries — `node_modules` is symlinked, not copied — but a stub type surface
 * instead of the Administration's full `src/`. That keeps the integration specs
 * a real `tsc` / `eslint` run while staying independent of the thousands of
 * files the host program would otherwise pull in.
 */
export function createStubAdministration(projectRoot: string, relativePath = 'admin'): string {
    const administrationRoot = path.join(projectRoot, relativePath);
    const presetDir = path.join(administrationRoot, 'extension-tooling');
    const realPresetDir = path.join(REAL_ADMINISTRATION_ROOT, 'extension-tooling');

    fs.mkdirSync(presetDir, { recursive: true });

    for (const preset of [
        'tsconfig.base.json',
        'eslint.mjs',
        'legacy-twig.mjs',
    ]) {
        fs.copyFileSync(path.join(realPresetDir, preset), path.join(presetDir, preset));
    }

    // Stands in for the real admin-types.d.ts: an extension sees exactly what the
    // Administration declares, and nothing else.
    writeTree(administrationRoot, {
        'extension-tooling/admin-types.d.ts': 'declare const Shopware: { name: string };\n',
    });

    fs.symlinkSync(path.join(REAL_ADMINISTRATION_ROOT, 'node_modules'), path.join(administrationRoot, 'node_modules'));

    return administrationRoot;
}

export interface ExtensionFixtureOptions {
    name: string;
    /** Files relative to the extension's Administration source root. */
    sources: Record<string, string>;
    composerName?: string;
    composerType?: string;
    /** Contents of a `tsconfig.json` beside the Administration source root. */
    tsconfig?: string;
}

export function createExtension(projectRoot: string, options: ExtensionFixtureOptions): { basePath: string } {
    const extensionRoot = path.join(projectRoot, 'custom', 'plugins', options.name);
    const adminFolder = path.join(extensionRoot, 'src', 'Resources', 'app', 'administration');

    writeTree(extensionRoot, {
        'composer.json': composerManifest(
            options.composerName ?? `fixture/${options.name.toLowerCase()}`,
            options.composerType ?? 'shopware-platform-plugin',
        ),
    });

    fs.mkdirSync(path.join(adminFolder, 'src'), { recursive: true });

    writeTree(
        path.join(adminFolder, 'src'),
        Object.fromEntries(
            Object.entries(options.sources).map(
                ([
                    file,
                    content,
                ]) => [
                    file,
                    content,
                ],
            ),
        ),
    );

    if (options.tsconfig !== undefined) {
        writeTree(adminFolder, { 'tsconfig.json': options.tsconfig });
    }

    return { basePath: path.join('custom', 'plugins', options.name, 'src') };
}

/** Every file below `root`, relative and POSIX, mapped to its content hash-ish size+content. */
export function snapshotTree(root: string, ignore: string[] = []): Record<string, string> {
    const snapshot: Record<string, string> = {};

    const walk = (directory: string): void => {
        for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
            const absolute = path.join(directory, entry.name);
            const relative = path.relative(root, absolute).split(path.sep).join('/');

            if (ignore.some((prefix) => relative === prefix || relative.startsWith(`${prefix}/`))) {
                continue;
            }

            if (entry.isSymbolicLink()) {
                snapshot[relative] = `symlink:${fs.readlinkSync(absolute)}`;

                continue;
            }

            if (entry.isDirectory()) {
                walk(absolute);

                continue;
            }

            snapshot[relative] = fs.readFileSync(absolute, 'utf8');
        }
    };

    walk(root);

    return snapshot;
}

/**
 * @sw-package framework
 *
 * Fixture builders for the extension tooling specs.
 *
 * `createSkeletonAdmin` builds a bare fake Administration: enough files for the
 * generator logic to resolve, with no real toolchain behind it.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';

export function writeFile(filePath: string, lines: string[] | string = ''): void {
    fs.mkdirSync(path.dirname(filePath), { recursive: true });
    fs.writeFileSync(filePath, Array.isArray(lines) ? `${lines.join('\n')}\n` : lines);
}

export function createTempProject(prefix: string): string {
    return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

/** Minimal fake Administration for generator-logic tests. */
export function createSkeletonAdmin(projectRoot: string): string {
    const administrationRoot = path.join(
        projectRoot,
        'vendor',
        'shopware',
        'administration',
        'Resources',
        'app',
        'administration',
    );

    for (const fileName of [
        'admin-types.d.ts',
        'eslint.mjs',
        'legacy-twig.mjs',
    ]) {
        writeFile(path.join(administrationRoot, 'extension-tooling', fileName));
    }

    writeFile(
        path.join(administrationRoot, 'extension-tooling', 'tsconfig.base.json'),
        `${JSON.stringify({ compilerOptions: { paths: { vue: ['../node_modules/vue'] } } })}\n`,
    );

    writeFile(
        path.join(administrationRoot, 'extension-tooling', 'host-modules.json'),
        `${JSON.stringify({ hostModules: { vue: 'node_modules/vue' } })}\n`,
    );
    writeFile(path.join(administrationRoot, 'node_modules', 'vue', 'package.json'), '{"name":"vue"}\n');
    writeFile(path.join(administrationRoot, 'package.json'), '{"name":"administration","version":"1.0.0"}\n');

    return administrationRoot;
}

/**
 * Stands in for the generated entity schema. Only one property matters to the
 * generator: it must not carry the generated marker, so it counts as the real
 * file rather than the tool's own stub.
 */
export const syntheticEntitySchema = [
    '/* THIS FILE IS AUTO GENERATED AND SHOULD NOT BE MODIFIED MANUALLY */',
    'declare namespace EntitySchema {',
    '    interface Entities {',
    '        product: { id: string };',
    '    }',
    '}',
];

export interface FixturePluginOptions {
    projectRoot: string;
    /** e.g. custom/plugins/ZeroConfig */
    pluginPath: string;
    withComposerJson?: boolean;
}

/**
 * A plugin with Administration sources and no config of its own. Nothing
 * compiles or lints these files — the generators only need the directory and an
 * entry file to exist — so the content stays minimal on purpose.
 */
export function writeZeroConfigPlugin(options: FixturePluginOptions): void {
    const pluginRoot = path.join(options.projectRoot, options.pluginPath);
    const sourceRoot = path.join(pluginRoot, 'src', 'Resources', 'app', 'administration', 'src');

    if (options.withComposerJson !== false) {
        writeFile(path.join(pluginRoot, 'composer.json'), '{}\n');
    }

    writeFile(path.join(sourceRoot, 'main.ts'), ['export {};']);
}

export interface FixtureBundleDefinition {
    technicalName: string;
    basePath: string;
    administrationPath?: string;
    entryFilePath?: string | null;
}

export function writePluginsConfig(projectRoot: string, bundles: FixtureBundleDefinition[]): string {
    const pluginsConfigPath = path.join(projectRoot, 'var', 'plugins.json');
    const entries = Object.fromEntries(
        bundles.map((bundle) => [
            bundle.technicalName,
            {
                technicalName: bundle.technicalName,
                basePath: bundle.basePath,
                administration: {
                    path: bundle.administrationPath ?? 'src/Resources/app/administration/src',
                    entryFilePath: bundle.entryFilePath ?? null,
                },
            },
        ]),
    );

    writeFile(pluginsConfigPath, `${JSON.stringify(entries, null, 2)}\n`);

    return pluginsConfigPath;
}

export function cleanupTempProject(projectRoot: string): void {
    fs.rmSync(projectRoot, { recursive: true, force: true });
}

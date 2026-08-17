/**
 * @sw-package framework
 *
 * Root-config bridge mode file-generation shape: one bridge beside a shared
 * package config (not one per root), per-root bridging retained for independent
 * layouts, existing configs never overwritten, ambiguity degraded to a warning
 * with the persisted `--root-config=<Extension>:<dir>` choice as the fix.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { SHIM_DIR_NAME } from '../shared';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    warningText,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

function countBridges(root: string): number {
    let count = 0;
    const queue = [root];

    while (queue.length > 0) {
        const current = queue.shift() as string;

        for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
            if (!entry.isDirectory() || entry.name === 'node_modules') {
                continue;
            }

            if (entry.name === SHIM_DIR_NAME && fs.existsSync(path.join(current, entry.name, 'tsconfig.json'))) {
                count += 1;
            }

            queue.push(path.join(current, entry.name));
        }
    }

    return count;
}

describe('scripts/extensionTooling/setup root-config bridge mode', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-rootcfg-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function writeMultiRootPlugin(name: string, bundles: string[], underGroup?: string): void {
        writeFile(path.join(projectRoot, `custom/plugins/${name}/composer.json`), '{}\n');

        writePluginsConfig(
            projectRoot,
            bundles.map((bundle) => {
                const relative = underGroup ? `${underGroup}/${bundle}` : bundle;

                writeFile(
                    path.join(
                        projectRoot,
                        `custom/plugins/${name}/src/${relative}/Resources/app/administration/src/main.ts`,
                    ),
                    ['export {};'],
                );

                return {
                    technicalName: `${name}${bundle}`,
                    basePath: `custom/plugins/${name}/src/${relative}`,
                    administrationPath: 'Resources/app/administration/src',
                };
            }),
        );
    }

    /** Two package-level configs, each governing two roots — undecidable without --root-config. */
    function writeAmbiguousPlugin(): void {
        writeMultiRootPlugin(
            'Ambiguous',
            [
                'BundleA',
                'BundleB',
            ],
            'GroupOne',
        );
        // Extend rather than replace so both groups keep their bundles.
        writeFile(path.join(projectRoot, 'custom/plugins/Ambiguous/composer.json'), '{}\n');

        for (const bundle of [
            'BundleC',
            'BundleD',
        ]) {
            writeFile(
                path.join(
                    projectRoot,
                    `custom/plugins/Ambiguous/src/GroupTwo/${bundle}/Resources/app/administration/src/main.ts`,
                ),
                ['export {};'],
            );
        }

        writePluginsConfig(
            projectRoot,
            [
                [
                    'GroupOne',
                    'BundleA',
                ],
                [
                    'GroupOne',
                    'BundleB',
                ],
                [
                    'GroupTwo',
                    'BundleC',
                ],
                [
                    'GroupTwo',
                    'BundleD',
                ],
            ].map(
                ([
                    group,
                    bundle,
                ]) => ({
                    technicalName: `Ambiguous${bundle}`,
                    basePath: `custom/plugins/Ambiguous/src/${group}/${bundle}`,
                    administrationPath: 'Resources/app/administration/src',
                }),
            ),
        );
        writeFile(path.join(projectRoot, 'custom/plugins/Ambiguous/src/GroupOne/tsconfig.json'), ['{ "files": [] }']);
        writeFile(path.join(projectRoot, 'custom/plugins/Ambiguous/src/GroupTwo/tsconfig.json'), ['{ "files": [] }']);
    }

    it('groups a zero-config multi-root plugin into one bridge per source root', () => {
        writeMultiRootPlugin('Mono', [
            'BundleA',
            'BundleB',
        ]);

        setupExtensionTooling({ projectRoot, administrationRoot });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Mono'))).toBe(2);
    });

    it('bridges once beside an explicit --root-config and scaffolds one covering config', () => {
        writeMultiRootPlugin('Mono', [
            'BundleA',
            'BundleB',
        ]);

        setupExtensionTooling({ projectRoot, administrationRoot, rootConfig: { extension: 'Mono', dir: '.' } });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Mono'))).toBe(1);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Mono/.shopware/tsconfig.json'))).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/Mono/src/BundleA/Resources/app/administration/tsconfig.json'),
            ),
        ).toBe(false);

        const scaffold = fs.readFileSync(path.join(projectRoot, 'custom/plugins/Mono/tsconfig.json'), 'utf8');

        expect(scaffold).toContain('"extends": "./.shopware/tsconfig.json"');
        expect(scaffold).toContain('src/BundleA/Resources/app/administration/src/**/*.ts');
        expect(scaffold).toContain('src/BundleB/Resources/app/administration/src/**/*.ts');

        // The scaffolded config makes the choice self-perpetuating: a plain
        // re-run groups both roots onto the same config directory again.
        const plainRerun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Mono'))).toBe(1);
        expect(plainRerun.changed).toBe(false);
    });

    it('shares one bridge for the package config that governs several roots', () => {
        writeMultiRootPlugin('Shared', [
            'BundleA',
            'BundleB',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Shared/tsconfig.json'), ['{ "files": [] }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Shared'))).toBe(1);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Shared/.shopware/tsconfig.json'))).toBe(true);
        // The pre-existing package config is never rewritten.
        expect(fs.readFileSync(path.join(projectRoot, 'custom/plugins/Shared/tsconfig.json'), 'utf8')).not.toContain(
            '@generated',
        );
        expect(warningText(result)).toContain('tsconfig.json already exists');
    });

    it('keeps one bridge per root for genuinely independent per-root configs', () => {
        writeMultiRootPlugin('Independent', [
            'BundleA',
            'BundleB',
        ]);

        for (const bundle of [
            'BundleA',
            'BundleB',
        ]) {
            writeFile(
                path.join(
                    projectRoot,
                    `custom/plugins/Independent/src/${bundle}/Resources/app/administration/tsconfig.json`,
                ),
                ['{ "files": [] }'],
            );
        }

        setupExtensionTooling({ projectRoot, administrationRoot });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Independent'))).toBe(2);
    });

    it('bridges each governing config of a package with several shared configs', () => {
        writeAmbiguousPlugin();

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        // Two package-level configs each governing two roots: one bridge each,
        // rather than a refusal that needs a flag to resolve.
        expect(countBridges(path.join(projectRoot, 'custom/plugins/Ambiguous'))).toBe(2);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Ambiguous/src/GroupOne/.shopware'))).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Ambiguous/src/GroupTwo/.shopware'))).toBe(true);
        expect(warningText(result)).not.toContain('was not bridged');
    });

    it('warns about a --root-config naming an unknown extension', () => {
        writeMultiRootPlugin('Known', [
            'BundleA',
            'BundleB',
        ]);

        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            rootConfig: { extension: 'Unknown', dir: '.' },
        });

        expect(warningText(result)).toContain('--root-config names the unknown extension Unknown');
    });
});

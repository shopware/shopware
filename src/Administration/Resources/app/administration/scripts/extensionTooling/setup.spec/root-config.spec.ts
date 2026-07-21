/**
 * @sw-package framework
 *
 * Root-config bridge mode file-generation shape: one bridge beside a shared
 * package config (not one per root), per-root shimming retained for independent
 * layouts, existing configs never overwritten, ambiguity refused. These are the
 * fast generator-logic assertions; the real-toolchain coverage lives in
 * e2e.spec/root-config-bridge.spec.ts.
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

    it('bridges once beside an explicit --root-config and scaffolds one covering config', () => {
        writeMultiRootPlugin('Mono', [
            'BundleA',
            'BundleB',
        ]);

        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'Mono', rootConfig: '.' });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Mono'))).toBe(1);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Mono/.shopware-admin/tsconfig.json'))).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/Mono/src/BundleA/Resources/app/administration/tsconfig.json'),
            ),
        ).toBe(false);

        const scaffold = fs.readFileSync(path.join(projectRoot, 'custom/plugins/Mono/tsconfig.json'), 'utf8');

        expect(scaffold).toContain('"extends": "./.shopware-admin/tsconfig.json"');
        expect(scaffold).toContain('src/BundleA/Resources/app/administration/src/**/*.ts');
        expect(scaffold).toContain('src/BundleB/Resources/app/administration/src/**/*.ts');
    });

    it('auto-detects a single shared package config governing multiple roots', () => {
        writeMultiRootPlugin('Shared', [
            'BundleA',
            'BundleB',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Shared/tsconfig.json'), ['{ "files": [] }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'Shared' });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Shared'))).toBe(1);
        expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Shared/.shopware-admin/tsconfig.json'))).toBe(true);
        // The pre-existing package config is never rewritten.
        expect(fs.readFileSync(path.join(projectRoot, 'custom/plugins/Shared/tsconfig.json'), 'utf8')).not.toContain(
            '@generated',
        );
        expect(result.warnings.some((warning) => warning.includes('tsconfig.json already exists'))).toBe(true);
    });

    it('keeps per-root shimming for genuinely independent per-root configs', () => {
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

        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'Independent' });

        expect(countBridges(path.join(projectRoot, 'custom/plugins/Independent'))).toBe(2);
    });

    it('refuses to auto-detect when two package configs each govern several roots', () => {
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

        expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'Ambiguous' })).toThrow(
            /more than one package-level config.*--root-config/s,
        );
    });
});

/**
 * @sw-package framework
 *
 * Automatic per-root bridging: the self-ignoring .shopware/ bridge generated
 * for every discovered extension (vendor included), the committable plugin
 * configs scaffolded beside it, alias merging into the bridge's paths, legacy
 * bridge cleanup, and the graceful fallback when a bridge cannot be written.
 * The multi-root variant lives in root-config.spec.ts.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { GENERATED_MARKER, LEGACY_SHIM_DIR_NAMES, deriveExtensionState } from '../shared';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup automatic bridging', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-bridge-'));
        writeDefaultFixtures(projectRoot);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('bridges every discovered extension automatically — vendor included', () => {
        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const shimDir = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware');
        const shimTsconfig = fs.readFileSync(path.join(shimDir, 'tsconfig.json'), 'utf8');

        expect(fs.readFileSync(path.join(shimDir, '.gitignore'), 'utf8')).toContain('*');
        expect(shimTsconfig).toContain('tsconfig.base.json');
        expect(shimTsconfig).toContain('admin-types.d.ts');
        expect(fs.readFileSync(path.join(shimDir, 'eslint.mjs'), 'utf8')).toContain('shopwareAdminExtension');

        // Multi-bundle suite with independent roots: one bridge per root.
        for (const bundle of [
            'BundleA',
            'BundleB',
        ]) {
            expect(
                fs.existsSync(
                    path.join(projectRoot, `custom/plugins/Suite/src/${bundle}/Resources/app/administration/.shopware`),
                ),
            ).toBe(true);
        }

        // Vendor extensions are bridged in place — no exceptions.
        expect(
            fs.existsSync(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/.shopware')),
        ).toBe(true);
        expect(result.writes.some((write) => write.file.includes('.shopware'))).toBe(true);
    });

    it('scaffolds committable plugin configs that extend the bridge and are never overwritten', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        setupExtensionTooling({ projectRoot, administrationRoot });

        const pluginTsconfig = fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8');
        const pluginEslint = fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8');

        // Committable: extends/imports the bridge, carries no generated marker.
        expect(pluginTsconfig).toContain('"extends": "./.shopware/tsconfig.json"');
        expect(pluginTsconfig).not.toContain(GENERATED_MARKER);
        expect(pluginEslint).toContain("import shopware from './.shopware/eslint.mjs'");

        // A developer edit survives a re-run, and the extension is discovered as bridged.
        fs.appendFileSync(path.join(adminFolder, 'eslint.config.mjs'), '// my custom rule\n');
        const rerun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8')).toContain('// my custom rule');
        expect(rerun.manifest.projects.find((project) => project.name === 'ZeroConfig')?.targets[0].bridgePresent).toBe(
            true,
        );
    });

    it('marks the scaffolds inside vendor extensions as local instead of committable', () => {
        setupExtensionTooling({ projectRoot, administrationRoot });

        // The vendor fixture ships its own configs, so its scaffolds are
        // skipped — remove them to see the scaffold content vendors get.
        const vendorAdminFolder = path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration');
        fs.rmSync(path.join(vendorAdminFolder, 'tsconfig.json'));
        fs.rmSync(path.join(vendorAdminFolder, 'eslint.config.mjs'));
        setupExtensionTooling({ projectRoot, administrationRoot });

        const vendorTsconfig = fs.readFileSync(path.join(vendorAdminFolder, 'tsconfig.json'), 'utf8');
        const customTsconfig = fs.readFileSync(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.json'),
            'utf8',
        );

        expect(vendorTsconfig).toContain('composer update removes this file');
        expect(vendorTsconfig).not.toContain('commit');
        expect(customTsconfig).toContain('commit');
    });

    it('never overwrites an existing plugin config and warns how to add the extends', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        writeFile(path.join(adminFolder, 'tsconfig.json'), ['{ "compilerOptions": { "strict": true } }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8')).toContain('"strict": true');
        expect(result.warnings.join('\n')).toContain('extends');
    });

    it('merges plugin aliases and preset host paths into the bridge tsconfig', () => {
        writeFile(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.aliases.json'),
            `${JSON.stringify({ 'ZeroConfig/*': ['src/*'] })}\n`,
        );
        setupExtensionTooling({ projectRoot, administrationRoot });

        const shimTsconfig = fs.readFileSync(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware/tsconfig.json'),
            'utf8',
        );
        const parsed = JSON.parse(shimTsconfig.split('\n').slice(1).join('\n')) as {
            compilerOptions: { paths: Record<string, string[]> };
        };

        expect(parsed.compilerOptions.paths['ZeroConfig/*']).toEqual(['../src/*']);
        expect(parsed.compilerOptions.paths.vue[0]).toContain('node_modules/vue');
    });

    it('generates a self-explaining README into the bridge and keeps it marker-owned', () => {
        const shimDir = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware');
        setupExtensionTooling({ projectRoot, administrationRoot });

        const readme = fs.readFileSync(path.join(shimDir, 'README.md'), 'utf8');

        // Marker within the first lines so writeManagedFile keeps ownership,
        // and the essentials a developer stumbling over the folder needs.
        expect(readme.split('\n')[0]).toContain(GENERATED_MARKER);
        expect(readme).toContain('do not edit');
        expect(readme).toContain('never');
        expect(readme).toContain('commit');
        // Layout-aware command (the fixture admin lives under vendor/, so the
        // bin/console form is printed).
        expect(readme).toContain('bin/console administration:setup-extension-tooling');
        // The self-ignoring .gitignore covers the README too.
        expect(fs.readFileSync(path.join(shimDir, '.gitignore'), 'utf8')).toContain('*');

        // A stale (outdated but marker-owned) README is rewritten on the next run.
        fs.writeFileSync(path.join(shimDir, 'README.md'), `<!-- ${GENERATED_MARKER} -->\nold content\n`);
        setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(shimDir, 'README.md'), 'utf8')).toContain('Why this folder exists');
    });

    it('deletes a marker-owned bridge of a previous tooling version', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        const legacyDir = path.join(adminFolder, LEGACY_SHIM_DIR_NAMES[0]);

        writeFile(path.join(legacyDir, 'tsconfig.json'), [
            `// ${GENERATED_MARKER}`,
            '{}',
        ]);
        writeFile(path.join(legacyDir, '.gitignore'), [
            `# ${GENERATED_MARKER}`,
            '*',
        ]);

        const checkResult = setupExtensionTooling({ projectRoot, administrationRoot, checkOnly: true });

        // The dry-run reports the pending deletion but leaves the directory alone.
        expect(checkResult.staleFiles.some((file) => file.endsWith(LEGACY_SHIM_DIR_NAMES[0]))).toBe(true);
        expect(fs.existsSync(legacyDir)).toBe(true);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.staleFiles.some((file) => file.endsWith(LEGACY_SHIM_DIR_NAMES[0]))).toBe(true);
        expect(fs.existsSync(legacyDir)).toBe(false);
        expect(fs.existsSync(path.join(adminFolder, '.shopware', 'tsconfig.json'))).toBe(true);
    });

    it('leaves a human-owned legacy bridge directory alone and warns', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        const legacyDir = path.join(adminFolder, LEGACY_SHIM_DIR_NAMES[0]);

        writeFile(path.join(legacyDir, 'tsconfig.json'), ['{ "compilerOptions": { "strict": true } }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.existsSync(path.join(legacyDir, 'tsconfig.json'))).toBe(true);
        expect(result.warnings.join('\n')).toContain('not marker-owned');
    });

    // chmod is advisory for root, so the read-only scenario cannot run there.
    const itSkippingRoot = process.getuid?.() === 0 ? it.skip : it;

    itSkippingRoot('falls back to host-managed configs when a bridge cannot be written', () => {
        const zeroConfigAdminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        const vendorAdminFolder = path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration');

        fs.chmodSync(zeroConfigAdminFolder, 0o555);
        fs.chmodSync(vendorAdminFolder, 0o555);

        try {
            const result = setupExtensionTooling({ projectRoot, administrationRoot });

            // The run completes; both failures degrade to warnings.
            expect(result.warnings.join('\n')).toContain('Could not write the bridge for ZeroConfig');
            expect(result.warnings.join('\n')).toContain('Could not write the bridge for custom-admin');

            // The zero-config plugin stays covered through the fallback leaf,
            // referenced from the root solution tsconfig.
            const zeroConfig = result.manifest.projects.find((project) => project.name === 'ZeroConfig');

            expect(zeroConfig && deriveExtensionState(zeroConfig)).toBe('ready');
            expect(zeroConfig?.targets[0].checkTsconfig).toContain('var/admin-extension-tooling/projects/');

            const leafPath = path.join(projectRoot, zeroConfig?.targets[0].checkTsconfig ?? '');

            expect(fs.existsSync(leafPath)).toBe(true);

            // The leaf's exclude patterns carry the source prefix, because
            // exclude resolves relative to the config file in var/.
            const leaf = JSON.parse(fs.readFileSync(leafPath, 'utf8').split('\n').slice(1).join('\n')) as {
                exclude: string[];
            };

            expect(leaf.exclude).toEqual(
                expect.arrayContaining([expect.stringMatching(/custom\/plugins\/ZeroConfig\/.*\*\*\/\*\.spec\.ts$/)]),
            );
            expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain(
                zeroConfig?.targets[0].checkTsconfig ?? '',
            );

            // Once writable again, the next run bridges and prunes the leaf.
            fs.chmodSync(zeroConfigAdminFolder, 0o755);
            const rerun = setupExtensionTooling({ projectRoot, administrationRoot });
            const bridgedZeroConfig = rerun.manifest.projects.find((project) => project.name === 'ZeroConfig');

            expect(bridgedZeroConfig && deriveExtensionState(bridgedZeroConfig)).toBe('bridged');
            expect(fs.existsSync(leafPath)).toBe(false);
            expect(rerun.staleFiles).toContain(zeroConfig?.targets[0].checkTsconfig ?? '');
        } finally {
            fs.chmodSync(zeroConfigAdminFolder, 0o755);
            fs.chmodSync(vendorAdminFolder, 0o755);
        }
    });
});

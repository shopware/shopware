/**
 * @sw-package framework
 *
 * Automatic bridging: the self-ignoring .shopware/ bridge generated for every
 * discovered extension (vendor included), the committable plugin configs
 * scaffolded beside it, alias merging into the bridge's paths, and the graceful
 * fallback when a bridge cannot be written. The multi-root grouping lives in
 * root-config.spec.ts.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { BRIDGE_ESLINT_SPECIFIER, BRIDGE_TSCONFIG_EXTENDS, GENERATED_MARKER, deriveExtensionState } from '../shared';
import { cleanupTempProject, warningText, writeFile } from '../test-helpers';
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
        expect(warningText(result)).toContain('extends');
    });

    it('names the actual reason an existing plugin config does not compose', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        // Extends the bridge but replaces its file list, so admin-types.d.ts
        // never enters the program — a different failure than a missing extends,
        // and the warning has to say so instead of printing the generic block.
        writeFile(path.join(adminFolder, 'tsconfig.json'), [
            `{ "extends": "${BRIDGE_TSCONFIG_EXTENDS}", "files": ["src/main.ts"] }`,
        ]);

        expect(warningText(setupExtensionTooling({ projectRoot, administrationRoot }))).toContain('"files" array');
    });

    it('names a missing "include" instead of leaving the sources silently unchecked', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        // Composes the bridge, but the inherited "files" is then the whole
        // program. Reported downstream as an opaque coverage tooling error, so
        // the warning has to name the missing "include" here.
        writeFile(path.join(adminFolder, 'tsconfig.json'), [`{ "extends": "${BRIDGE_TSCONFIG_EXTENDS}" }`]);

        // Scoped to ZeroConfig: the fixture's vendor extension genuinely misses
        // its extends, so the project-wide text carries that hint too.
        const warning = setupExtensionTooling({ projectRoot, administrationRoot })
            .warnings.filter((entry) => entry.extension === 'ZeroConfig')
            .map((entry) => entry.message)
            .join('\n');

        expect(warning).toContain('declares no "include"');
        expect(warning).toContain('"include": ["src/**/*.ts", "src/**/*.vue"]');
        // It already has its extends, so the remediation must not ask for one.
        expect(warning).not.toContain('add "extends"');
    });

    it('stays silent about an existing plugin config that already composes the bridge', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        // A wired plugin must not be nagged on every run — the warning asks
        // whether the config composes, not merely whether a file is there.
        writeFile(path.join(adminFolder, 'tsconfig.json'), [
            `{ "extends": "${BRIDGE_TSCONFIG_EXTENDS}", "include": ["src/**/*.ts"] }`,
        ]);
        writeFile(path.join(adminFolder, 'eslint.config.mjs'), [
            `import shopware from '${BRIDGE_ESLINT_SPECIFIER}';`,
            'export default [...shopware];',
        ]);

        // The fixture's vendor extension ships genuinely non-composing configs
        // and must keep warning, so only ZeroConfig's own lines may disappear.
        const zeroConfigWarnings = (): string[] =>
            setupExtensionTooling({ projectRoot, administrationRoot })
                .warnings.filter((warning) => warning.extension === 'ZeroConfig')
                .map((warning) => warning.message);

        expect(zeroConfigWarnings()).toEqual([]);
        // Re-running is the documented migration path, so the second run has to
        // be quiet too — the first one wrote the bridge these configs point at.
        expect(zeroConfigWarnings()).toEqual([]);
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

    it('falls back to the root projection when a bridge cannot be written', () => {
        // chmod is advisory for root, so the read-only scenario cannot run there.
        if (process.getuid?.() === 0) {
            return;
        }

        const zeroConfigAdminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        const vendorAdminFolder = path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration');

        fs.chmodSync(zeroConfigAdminFolder, 0o555);
        fs.chmodSync(vendorAdminFolder, 0o555);

        try {
            const result = setupExtensionTooling({ projectRoot, administrationRoot });

            // The run completes; both failures degrade to warnings.
            expect(warningText(result)).toContain('Could not write the bridge for ZeroConfig');
            expect(warningText(result)).toContain('Could not write the bridge for custom-admin');

            const zeroConfig = result.manifest.projects.find((project) => project.name === 'ZeroConfig');

            expect(zeroConfig && deriveExtensionState(zeroConfig)).toBe('ready');
            expect(zeroConfig?.targets[0].tsconfig).toBeNull();

            // The source root is covered by the root tsconfig's own include,
            // with its spec files excluded.
            const rootTsconfig = JSON.parse(
                fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8').split('\n').slice(1).join('\n'),
            ) as { include: string[]; exclude: string[] };
            const sourcePath = zeroConfig?.targets[0].sourcePath ?? '';

            expect(rootTsconfig.include).toContain(`${sourcePath}/**/*.ts`);
            expect(rootTsconfig.exclude).toContain(`${sourcePath}/**/*.spec.ts`);

            // Once writable again, the next run bridges and the projection empties.
            fs.chmodSync(zeroConfigAdminFolder, 0o755);
            const rerun = setupExtensionTooling({ projectRoot, administrationRoot });
            const bridged = rerun.manifest.projects.find((project) => project.name === 'ZeroConfig');

            expect(bridged && deriveExtensionState(bridged)).toBe('bridged');
            expect(
                (
                    JSON.parse(
                        fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8').split('\n').slice(1).join('\n'),
                    ) as { include: string[] }
                ).include,
            ).not.toContain(`${sourcePath}/**/*.ts`);
        } finally {
            fs.chmodSync(zeroConfigAdminFolder, 0o755);
            fs.chmodSync(vendorAdminFolder, 0o755);
        }
    });
});

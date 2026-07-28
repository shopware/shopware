/**
 * @sw-package framework
 *
 * Per-root `--shim` bridging: the self-ignoring .shopware-admin/ bridge, the
 * committable plugin configs scaffolded beside it, alias merging into the
 * bridge's paths, and the refusals (vendor/platform roots, unknown names).
 * The multi-root variant lives in root-config.spec.ts.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { GENERATED_MARKER } from '../shared';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup shim bridging', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-shim-'));
        writeDefaultFixtures(projectRoot);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('generates self-ignoring shims only below custom/plugins', () => {
        const result = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });
        const shimDir = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin');
        const shimTsconfig = fs.readFileSync(path.join(shimDir, 'tsconfig.json'), 'utf8');

        expect(fs.readFileSync(path.join(shimDir, '.gitignore'), 'utf8')).toContain('*');
        expect(shimTsconfig).toContain('tsconfig.base.json');
        expect(shimTsconfig).toContain('admin-types.d.ts');
        expect(fs.readFileSync(path.join(shimDir, 'eslint.mjs'), 'utf8')).toContain('shopwareAdminExtension');
        expect(result.writes.some((write) => write.file.includes('.shopware-admin'))).toBe(true);

        expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'CustomAdmin' })).toThrow(
            /only generated below custom\/plugins/,
        );
        expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'DoesNotExist' })).toThrow(
            /No extension matches/,
        );
    });

    it('scaffolds committable plugin configs that extend the bridge and are never overwritten', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        const pluginTsconfig = fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8');
        const pluginEslint = fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8');

        // Committable: extends/imports the bridge, carries no generated marker.
        expect(pluginTsconfig).toContain('"extends": "./.shopware-admin/tsconfig.json"');
        expect(pluginTsconfig).not.toContain(GENERATED_MARKER);
        expect(pluginEslint).toContain("import shopware from './.shopware-admin/eslint.mjs'");

        // A developer edit survives a re-run, and the extension is discovered as bridged.
        fs.appendFileSync(path.join(adminFolder, 'eslint.config.mjs'), '// my custom rule\n');
        const rerun = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        expect(fs.readFileSync(path.join(adminFolder, 'eslint.config.mjs'), 'utf8')).toContain('// my custom rule');
        expect(rerun.manifest.projects.find((project) => project.name === 'ZeroConfig')?.targets[0].bridgePresent).toBe(
            true,
        );
    });

    it('never overwrites an existing plugin config and warns how to add the extends', () => {
        const adminFolder = path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration');

        writeFile(path.join(adminFolder, 'tsconfig.json'), ['{ "compilerOptions": { "strict": true } }']);

        const result = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        expect(fs.readFileSync(path.join(adminFolder, 'tsconfig.json'), 'utf8')).toContain('"strict": true');
        expect(result.warnings.join('\n')).toContain('extends');
    });

    it('merges plugin aliases and preset host paths into the shim tsconfig', () => {
        writeFile(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/tsconfig.aliases.json'),
            `${JSON.stringify({ 'ZeroConfig/*': ['src/*'] })}\n`,
        );
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ZeroConfig' });

        const shimTsconfig = fs.readFileSync(
            path.join(
                projectRoot,
                'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin/tsconfig.json',
            ),
            'utf8',
        );
        const parsed = JSON.parse(shimTsconfig.split('\n').slice(1).join('\n')) as {
            compilerOptions: { paths: Record<string, string[]> };
        };

        expect(parsed.compilerOptions.paths['ZeroConfig/*']).toEqual(['../src/*']);
        expect(parsed.compilerOptions.paths.vue[0]).toContain('node_modules/vue');
    });

    it('generates shims for every writable extension with --shim=all-custom', () => {
        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'all-custom' });

        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/Suite/src/BundleA/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(true);
        expect(
            fs.existsSync(
                path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/.shopware-admin'),
            ),
        ).toBe(false);
    });
});

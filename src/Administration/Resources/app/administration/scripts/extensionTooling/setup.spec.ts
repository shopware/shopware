/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { GENERATED_MARKER, setupExtensionTooling } from './setup';

function writeFile(filePath: string, content = ''): void {
    fs.mkdirSync(path.dirname(filePath), { recursive: true });
    fs.writeFileSync(filePath, content);
}

describe('Administration extension tooling setup', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-admin-tooling-'));
        administrationRoot = path.join(
            projectRoot,
            'vendor',
            'shopware',
            'administration',
            'Resources',
            'app',
            'administration',
        );

        for (const fileName of [
            'eslint.mjs',
            'legacy-twig.mjs',
            'tsconfig.json',
            'types.d.ts',
        ]) {
            writeFile(path.join(administrationRoot, 'extension-tooling', fileName));
        }
        writeFile(path.join(administrationRoot, 'extension-tooling', 'public-api.d.ts'));
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('creates a zero-config bridge for source, custom, and vendor extension layouts', () => {
        const zeroConfigSource = path.join(
            projectRoot,
            'custom',
            'plugins',
            'ZeroConfig',
            'src',
            'Resources',
            'app',
            'administration',
            'src',
        );
        const customSource = path.join(
            projectRoot,
            'vendor',
            'acme',
            'custom-admin',
            'src',
            'Resources',
            'app',
            'administration',
            'src',
        );
        const storefrontAdminSource = path.join(
            projectRoot,
            'src',
            'Storefront',
            'Resources',
            'app',
            'administration',
            'src',
        );
        const customTsconfig = path.join(customSource, '..', 'tsconfig.json');
        const customEslintConfig = path.join(customSource, '..', 'eslint.config.mjs');

        writeFile(path.join(zeroConfigSource, 'main.ts'), 'Shopware.Service("repositoryFactory");\n');
        writeFile(path.join(customSource, 'main.ts'), 'Shopware.Service("repositoryFactory");\n');
        writeFile(path.join(storefrontAdminSource, 'main.js'));
        writeFile(customTsconfig, '{}\n');
        writeFile(customEslintConfig, 'export default [];\n');

        const pluginsConfigPath = path.join(projectRoot, 'var', 'plugins.json');
        writeFile(
            pluginsConfigPath,
            JSON.stringify(
                Object.fromEntries(
                    [
                        {
                            technicalName: 'administration',
                            basePath: path.relative(projectRoot, path.resolve(administrationRoot, '../../..')),
                            administration: {
                                path: 'Resources/app/administration/src',
                            },
                        },
                        {
                            technicalName: 'ZeroConfig',
                            basePath: 'custom/plugins/ZeroConfig/src',
                            administration: {
                                path: 'Resources/app/administration/src',
                            },
                        },
                        {
                            technicalName: 'CustomAdmin',
                            basePath: 'vendor/acme/custom-admin/src',
                            administration: {
                                path: 'Resources/app/administration/src',
                            },
                        },
                        {
                            technicalName: 'storefront',
                            basePath: 'src/Storefront',
                            administration: {
                                path: 'Resources/app/administration/src',
                            },
                        },
                    ].map((bundle) => [
                        bundle.technicalName,
                        bundle,
                    ]),
                ),
            ),
        );

        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            pluginsConfigPath,
        });

        expect(result.manifest.projects).toHaveLength(3);
        expect(result.manifest.administrationRoot).toBe('vendor/shopware/administration/Resources/app/administration');
        expect(result.manifest.entitySchemaAvailable).toBe(true);

        const zeroConfigProject = result.manifest.projects.find((project) => project.technicalName === 'ZeroConfig');
        const customProject = result.manifest.projects.find((project) => project.technicalName === 'CustomAdmin');

        expect(zeroConfigProject).toMatchObject({
            tsconfig: null,
            eslintConfig: null,
            mode: 'default',
        });
        expect(customProject).toMatchObject({
            tsconfig: 'vendor/acme/custom-admin/src/Resources/app/administration/tsconfig.json',
            eslintConfig: 'vendor/acme/custom-admin/src/Resources/app/administration/eslint.config.mjs',
            mode: 'custom',
        });

        const rootTsconfig = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');

        expect(rootTsconfig).toContain(GENERATED_MARKER);
        expect(rootTsconfig).toContain('custom/plugins/ZeroConfig');
        expect(rootTsconfig).toContain('src/Storefront/Resources/app/administration/src');
        expect(rootTsconfig).not.toContain('vendor/acme/custom-admin');

        const bridgeRoot = path.join(projectRoot, 'node_modules', '@shopware-ag', 'administration-extension-tooling');
        const bridgeTsconfig = fs.readFileSync(path.join(bridgeRoot, 'tsconfig.json'), 'utf8');
        const bridgeEslintConfig = fs.readFileSync(path.join(bridgeRoot, 'eslint.mjs'), 'utf8');

        expect(bridgeTsconfig).toContain('vendor/shopware/administration');
        expect(bridgeTsconfig).not.toContain(projectRoot);
        expect(bridgeEslintConfig).toContain('vendor/shopware/administration');
        expect(bridgeEslintConfig).not.toContain(projectRoot);
        expect(fs.existsSync(result.manifestPath)).toBe(true);
    });

    it('preserves root configs that are not owned by the generator', () => {
        const pluginsConfigPath = path.join(projectRoot, 'var', 'plugins.json');

        writeFile(pluginsConfigPath, '[]\n');
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{"strict":false}}\n');
        writeFile(path.join(projectRoot, 'eslint.config.mjs'), 'export default [];\n');

        const result = setupExtensionTooling({
            projectRoot,
            administrationRoot,
            pluginsConfigPath,
        });

        expect(result.manifest.rootTsconfigManaged).toBe(false);
        expect(result.manifest.rootEslintConfigManaged).toBe(false);
        expect(result.warnings).toHaveLength(2);
        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('"strict":false');
        expect(fs.readFileSync(path.join(projectRoot, 'eslint.config.mjs'), 'utf8')).toBe('export default [];\n');
    });
});

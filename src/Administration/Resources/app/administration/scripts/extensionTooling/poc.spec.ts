/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { spawnSync } from 'child_process';

function writeFile(filePath: string, lines: string[] | string = ''): void {
    fs.mkdirSync(path.dirname(filePath), { recursive: true });
    fs.writeFileSync(filePath, Array.isArray(lines) ? lines.join('\n') + '\n' : lines);
}

describe('Administration extension tooling proof of concept', () => {
    const administrationRoot = path.resolve(__dirname, '../..');
    const checkScript = path.join(administrationRoot, 'scripts', 'extensionTooling', 'check.ts');
    const buildScript = path.join(administrationRoot, 'build', 'plugins.vite.ts');
    const tsNodeScript = path.join(administrationRoot, 'node_modules', 'ts-node', 'dist', 'bin.js');
    let projectRoot: string;
    let vendorAdministrationRoot: string;
    let pluginsConfigPath: string;
    let zeroConfigMainPath: string;

    function runCheck(additionalArguments: string[] = []) {
        return spawnSync(
            process.execPath,
            [
                tsNodeScript,
                '--transpileOnly',
                checkScript,
                '--project-root=' + projectRoot,
                '--administration-root=' + vendorAdministrationRoot,
                '--plugins-config=' + pluginsConfigPath,
                ...additionalArguments,
            ],
            {
                cwd: administrationRoot,
                encoding: 'utf8',
                maxBuffer: 20 * 1024 * 1024,
            },
        );
    }

    function runBuild() {
        return spawnSync(
            process.execPath,
            [
                tsNodeScript,
                '--transpileOnly',
                buildScript,
            ],
            {
                cwd: administrationRoot,
                encoding: 'utf8',
                env: {
                    ...process.env,
                    PROJECT_ROOT: projectRoot,
                    VITE_MODE: 'production',
                },
                maxBuffer: 20 * 1024 * 1024,
            },
        );
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-admin-tooling-poc-'));
        vendorAdministrationRoot = path.join(
            projectRoot,
            'vendor',
            'shopware',
            'administration',
            'Resources',
            'app',
            'administration',
        );
        fs.mkdirSync(path.join(vendorAdministrationRoot, 'extension-tooling'), { recursive: true });

        for (const toolingFile of [
            'eslint.mjs',
            'legacy-twig.mjs',
            'tsconfig.json',
            'types.d.ts',
            'public-api.d.ts',
        ]) {
            fs.copyFileSync(
                path.join(administrationRoot, 'extension-tooling', toolingFile),
                path.join(vendorAdministrationRoot, 'extension-tooling', toolingFile),
            );
        }

        for (const linkedDirectory of [
            'eslint-rules',
            'node_modules',
        ]) {
            fs.symlinkSync(
                path.join(administrationRoot, linkedDirectory),
                path.join(vendorAdministrationRoot, linkedDirectory),
                'dir',
            );
        }

        fs.mkdirSync(path.join(vendorAdministrationRoot, 'src'), { recursive: true });
        fs.copyFileSync(
            path.join(administrationRoot, 'src', 'html-shim.d.ts'),
            path.join(vendorAdministrationRoot, 'src', 'html-shim.d.ts'),
        );
        writeFile(path.join(vendorAdministrationRoot, 'src', 'entity-schema-definition.d.ts'), [
            'declare namespace EntitySchema {',
            '    interface Entities {',
            '        product: product;',
            '        acme_custom_entity: acme_custom_entity;',
            '    }',
            '',
            '    interface product {',
            '        id: string;',
            '        name?: string;',
            '    }',
            '',
            '    interface acme_custom_entity {',
            '        id: string;',
            '        customValue: string;',
            '    }',
            '}',
        ]);

        const zeroConfigRoot = path.join(projectRoot, 'custom', 'plugins', 'ZeroConfig');
        const zeroConfigSource = path.join(zeroConfigRoot, 'src', 'Resources', 'app', 'administration', 'src');
        zeroConfigMainPath = path.join(zeroConfigSource, 'main.ts');
        writeFile(zeroConfigMainPath, [
            "import ProductCard from './ProductCard.vue';",
            '',
            "const productRepository = Shopware.Service('repositoryFactory').create('product');",
            "const customEntityRepository = Shopware.Service('repositoryFactory').create('acme_custom_entity');",
            'const criteria = new Shopware.Data.Criteria(1, 10);',
            'void ProductCard;',
            '',
            "export async function loadProduct(productId: string): Promise<Entity<'product'> | null> {",
            '    const products = await productRepository.search(criteria, Shopware.Context.api);',
            '    return products.get(productId);',
            '}',
            '',
            "export const customEntity: Entity<'acme_custom_entity'> = customEntityRepository.create(",
            '    Shopware.Context.api,',
            "    'custom-id',",
            ');',
        ]);
        writeFile(path.join(zeroConfigSource, 'ProductCard.vue'), [
            '<script setup lang="ts">',
            'const props = defineProps<{ productId: string }>();',
            "const repository = Shopware.Service('repositoryFactory').create('product');",
            '',
            'async function loadProduct() {',
            '    return repository.get(props.productId, Shopware.Context.api);',
            '}',
            '',
            'void loadProduct();',
            '</script>',
            '',
            '<template>',
            '    <p class="zero-config-product-card">',
            '        {{ props.productId }}',
            '    </p>',
            '</template>',
        ]);
        writeFile(path.join(zeroConfigSource, 'product-card.html.twig'), [
            '<div class="zero-config-product-card">',
            '    {% block zero_config_product_card_content %}',
            '        <span>{{ product.name }}</span>',
            '    {% endblock %}',
            '</div>',
        ]);

        const customRoot = path.join(projectRoot, 'vendor', 'acme', 'custom-admin');
        const customAdministrationRoot = path.join(customRoot, 'src', 'Resources', 'app', 'administration');
        writeFile(path.join(customRoot, 'composer.json'), '{}\n');
        writeFile(path.join(customRoot, 'package.json'), '{"private":true}\n');
        writeFile(path.join(customAdministrationRoot, 'tsconfig.json'), [
            '{',
            '  "extends": "@shopware-ag/administration-extension-tooling/tsconfig",',
            '  "include": ["src/**/*.ts"]',
            '}',
        ]);
        writeFile(path.join(customAdministrationRoot, 'eslint.config.mjs'), [
            "import { shopwareAdminExtension } from '@shopware-ag/administration-extension-tooling/eslint';",
            '',
            'export default [',
            '    ...shopwareAdminExtension({ legacyTwig: false }),',
            '    { files: ["**/*.ts"], rules: { "no-console": "error" } },',
            '];',
        ]);
        writeFile(path.join(customAdministrationRoot, 'src', 'main.ts'), [
            "const repository = Shopware.Service('repositoryFactory').create('product');",
            "export const newProduct = repository.create(Shopware.Context.api, 'custom-id');",
        ]);

        const suiteRoot = path.join(projectRoot, 'custom', 'plugins', 'Suite');
        writeFile(path.join(suiteRoot, 'composer.json'), '{}\n');
        writeFile(path.join(suiteRoot, 'tsconfig.json'), [
            '{',
            '  "extends": "@shopware-ag/administration-extension-tooling/tsconfig",',
            '  "include": ["src/**/Resources/app/administration/src/**/*.ts"]',
            '}',
        ]);
        writeFile(path.join(suiteRoot, 'eslint.config.mjs'), [
            "import { shopwareAdminExtension } from '@shopware-ag/administration-extension-tooling/eslint';",
            'export default shopwareAdminExtension({ legacyTwig: false });',
        ]);

        for (const bundleName of [
            'BundleA',
            'BundleB',
        ]) {
            writeFile(path.join(suiteRoot, 'src', bundleName, 'Resources', 'app', 'administration', 'src', 'main.ts'), [
                "const repository = Shopware.Service('repositoryFactory').create('product');",
                'export const entityName = repository.entityName;',
            ]);
        }

        pluginsConfigPath = path.join(projectRoot, 'var', 'plugins.json');
        writeFile(
            pluginsConfigPath,
            JSON.stringify(
                {
                    ZeroConfig: {
                        technicalName: 'ZeroConfig',
                        basePath: 'custom/plugins/ZeroConfig',
                        administration: {
                            path: 'src/Resources/app/administration/src',
                            entryFilePath: 'src/Resources/app/administration/src/main.ts',
                        },
                    },
                    CustomAdmin: {
                        technicalName: 'CustomAdmin',
                        basePath: 'vendor/acme/custom-admin',
                        administration: {
                            path: 'src/Resources/app/administration/src',
                        },
                    },
                    SuiteA: {
                        technicalName: 'SuiteA',
                        basePath: 'custom/plugins/Suite/src/BundleA',
                        administration: {
                            path: 'Resources/app/administration/src',
                        },
                    },
                    SuiteB: {
                        technicalName: 'SuiteB',
                        basePath: 'custom/plugins/Suite/src/BundleB',
                        administration: {
                            path: 'Resources/app/administration/src',
                        },
                    },
                },
                null,
                2,
            ) + '\n',
        );
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('checks zero-config, custom, multi-bundle, SFC, Twig, and vendor Administration layouts', () => {
        const validResult = runCheck();

        if (validResult.status !== 0) {
            throw new Error((validResult.stdout ?? '') + '\n' + (validResult.stderr ?? ''));
        }

        expect(validResult.status).toBe(0);
        expect(validResult.stdout).toContain('Administration extension checks: 0 new');

        const buildResult = runBuild();

        if (buildResult.status !== 0) {
            throw new Error((buildResult.stdout ?? '') + '\n' + (buildResult.stderr ?? ''));
        }

        expect(buildResult.status).toBe(0);
        expect(
            fs.existsSync(
                path.join(
                    projectRoot,
                    'custom',
                    'plugins',
                    'ZeroConfig',
                    'Resources',
                    'public',
                    'administration',
                    '.vite',
                    'manifest.json',
                ),
            ),
        ).toBe(true);

        fs.appendFileSync(zeroConfigMainPath, "const invalidProductId: number = 'invalid';\n");
        const invalidResult = runCheck();

        expect(invalidResult.status).toBe(1);
        expect(invalidResult.stderr).toContain('TS2322');

        const baselineResult = runCheck(['--update-baseline=ZeroConfig']);

        if (baselineResult.status !== 0) {
            throw new Error((baselineResult.stdout ?? '') + '\n' + (baselineResult.stderr ?? ''));
        }

        expect(baselineResult.status).toBe(0);
        expect(baselineResult.stdout).toContain('2 baselined');
        expect(
            fs.existsSync(path.join(projectRoot, 'custom', 'plugins', 'ZeroConfig', '.shopware-admin-baseline.json')),
        ).toBe(true);

        const repeatedResult = runCheck();

        expect(repeatedResult.status).toBe(0);
        expect(repeatedResult.stdout).toContain('2 baselined');
    });
});

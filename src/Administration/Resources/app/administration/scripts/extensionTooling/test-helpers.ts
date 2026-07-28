/**
 * @sw-package framework
 *
 * Fixture builders for the extension tooling specs.
 *
 * Two flavors of fake Administration:
 * - `createSkeletonAdmin`: bare files for generator-logic tests (no type
 *   checking possible).
 * - `createVendorAdmin`: a Flex-style vendor install that can really run
 *   vue-tsc/ESLint — the real node_modules are symlinked, the real src is
 *   entry-symlinked, and global.types.ts/html-shim.d.ts are copied so the
 *   entity schema can be controlled per test.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { EntitySchemaConverter } from '../entitySchemaConverter/entity-schema-converter';

export const realAdministrationRoot = path.resolve(__dirname, '../..');

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

export const syntheticEntitySchema = [
    '/* eslint-disable */',
    '/* THIS FILE IS AUTO GENERATED AND SHOULD NOT BE MODIFIED MANUALLY */',
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
];

/**
 * Generates a real, installation-shaped entity schema d.ts from the dumped
 * schema mock (test/_mocks_/entity-schema.json — present locally after
 * `composer framework:schema:dump` and always dumped in CI before jest).
 * The live-types surface compiles the Administration source graph, which
 * references real entities — a synthetic mini-schema would break it.
 */
export function writeConvertedEntitySchema(targetPath: string): void {
    const schemaMockPath = path.join(realAdministrationRoot, 'test', '_mocks_', 'entity-schema.json');

    if (!fs.existsSync(schemaMockPath)) {
        throw new Error(
            `${schemaMockPath} is missing — run \`composer framework:schema:dump\` before the extension tooling e2e suite.`,
        );
    }

    const entitySchema = JSON.parse(fs.readFileSync(schemaMockPath, 'utf8')) as Record<string, unknown>;

    fs.mkdirSync(path.dirname(targetPath), { recursive: true });
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-explicit-any
    new EntitySchemaConverter().convert(entitySchema as any, targetPath);
}

/**
 * Fake vendor-installed Administration that can run the real toolchain.
 * The entity schema is generated from the schema mock (or omitted) so schema
 * scenarios are testable without touching the real Administration.
 */
export function createVendorAdmin(projectRoot: string, options: { entitySchema: 'real' | 'none' }): string {
    const administrationRoot = path.join(
        projectRoot,
        'vendor',
        'shopware',
        'administration',
        'Resources',
        'app',
        'administration',
    );
    const sourceRoot = path.join(administrationRoot, 'src');

    fs.mkdirSync(path.join(administrationRoot, 'extension-tooling'), { recursive: true });
    fs.mkdirSync(sourceRoot, { recursive: true });

    for (const toolingFile of fs.readdirSync(path.join(realAdministrationRoot, 'extension-tooling'))) {
        fs.copyFileSync(
            path.join(realAdministrationRoot, 'extension-tooling', toolingFile),
            path.join(administrationRoot, 'extension-tooling', toolingFile),
        );
    }

    // Copied (not symlinked) so their relative '../src/…' imports resolve
    // inside the fake admin, where the entity schema is controlled by tests.
    const copiedSources = [
        'global.types.ts',
        'html-shim.d.ts',
    ];

    for (const copiedSource of copiedSources) {
        fs.copyFileSync(path.join(realAdministrationRoot, 'src', copiedSource), path.join(sourceRoot, copiedSource));
    }

    for (const sourceEntry of fs.readdirSync(path.join(realAdministrationRoot, 'src'))) {
        if (copiedSources.includes(sourceEntry) || sourceEntry === 'entity-schema-definition.d.ts') {
            continue;
        }

        fs.symlinkSync(path.join(realAdministrationRoot, 'src', sourceEntry), path.join(sourceRoot, sourceEntry));
    }

    if (options.entitySchema === 'real') {
        writeConvertedEntitySchema(path.join(sourceRoot, 'entity-schema-definition.d.ts'));
    }

    fs.symlinkSync(path.join(realAdministrationRoot, 'node_modules'), path.join(administrationRoot, 'node_modules'), 'dir');
    fs.symlinkSync(path.join(realAdministrationRoot, 'eslint-rules'), path.join(administrationRoot, 'eslint-rules'), 'dir');
    fs.copyFileSync(path.join(realAdministrationRoot, 'package.json'), path.join(administrationRoot, 'package.json'));

    return administrationRoot;
}

export interface FixturePluginOptions {
    projectRoot: string;
    /** e.g. custom/plugins/ZeroConfig */
    pluginPath: string;
    withComposerJson?: boolean;
}

/** Writes a typed zero-config plugin: TS entry + `<script setup>` SFC + legacy Twig template + entity merge. */
export function writeZeroConfigPlugin(options: FixturePluginOptions): void {
    const pluginRoot = path.join(options.projectRoot, options.pluginPath);
    const sourceRoot = path.join(pluginRoot, 'src', 'Resources', 'app', 'administration', 'src');

    if (options.withComposerJson !== false) {
        writeFile(path.join(pluginRoot, 'composer.json'), '{}\n');
    }

    writeFile(path.join(sourceRoot, 'main.ts'), [
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
    writeFile(path.join(sourceRoot, 'global.d.ts'), [
        'declare global {',
        '    namespace EntitySchema {',
        '        interface Entities {',
        '            fixture_plugin_entity: { id: string; fixtureValue: string };',
        '            acme_custom_entity: { id: string; customValue: string };',
        '        }',
        '    }',
        '}',
        '',
        'export {};',
    ]);
    writeFile(path.join(sourceRoot, 'PluginEntityUsage.ts'), [
        "export const fixtureRepo = Shopware.Service('repositoryFactory').create('fixture_plugin_entity');",
    ]);
    writeFile(path.join(sourceRoot, 'ProductCard.vue'), [
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
    writeFile(path.join(sourceRoot, 'product-card.html.twig'), [
        '<div class="zero-config-product-card">',
        '    {% block zero_config_product_card_content %}',
        '        <span>{{ product.name }}</span>',
        '    {% endblock %}',
        '</div>',
    ]);
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

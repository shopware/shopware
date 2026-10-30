/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;
const eager =
    "import config from './config'; const { Component: Registry } = Shopware; Registry.override('x', () => config); import.meta.glob('./components/*.ts', { eager: true });";
const lazy =
    "const { Component: Registry } = Shopware; Registry.override('x', () => import('./config')); import.meta.glob('./components/*.ts');";
const shadowed = "import config from './config'; function register(Component) { Component.register('x', () => config); }";
const vue = `<script setup lang="ts">${eager}</script><template><div /></template>`;

// Exercise the published factory through Node, including its CommonJS rule bridge.
// Type-aware blocks require a generated extension project and are covered separately.
const probe = `
import { ESLint } from 'eslint';
const { shopwareAdminExtension, tseslint } = await import(${JSON.stringify(factoryUrl)});
function config(severity, extensionRoots = []) {
    const blocks = shopwareAdminExtension({ tsconfigRootDir: process.cwd(), loadingPerformanceSeverity: severity, extensionRoots });
    return [
        ...blocks.filter(block => block.name === 'shopware/admin-extension/runtime-contract' ||
            block.name?.startsWith('shopware/admin-extension/loading-performance') ||
            (block.name?.startsWith('shopware/admin-extension/vue-') &&
                !['shopware/admin-extension/vue-typescript', 'shopware/admin-extension/vue-component-type-unsafety'].includes(block.name))),
        { files: ['**/*.ts'], languageOptions: { parser: tseslint.parser } },
        { files: ['**/*.vue'], languageOptions: { parserOptions: { parser: tseslint.parser } } },
    ];
}
async function lint(code, filePath, severity, roots) {
    const eslint = new ESLint({ overrideConfigFile: true, overrideConfig: config(severity, roots) });
    const [report] = await eslint.lintText(code, { filePath });
    if (report.messages.some(message => message.fatal)) throw Error(JSON.stringify(report.messages));
    return report.messages.filter(message => message.ruleId?.startsWith('plugin-rules/no-eager-'))
        .map(message => ({ rule: message.ruleId, severity: message.severity }));
}
const root = 'custom/plugins/Demo/src/Resources/app/administration/src';
const result = {
    defaults: await lint(${JSON.stringify(eager)}, 'main.js', undefined),
    warnings: await lint(${JSON.stringify(eager)}, 'main.ts', 'warn'),
    errors: await lint(${JSON.stringify(eager)}, 'main.js', 'error'),
    lazy: await lint(${JSON.stringify(lazy)}, 'main.ts', 'warn'),
    shadowed: await lint(${JSON.stringify(shadowed)}, 'main.js', 'warn'),
    vue: await lint(${JSON.stringify(vue)}, 'sw-demo.vue', 'warn'),
    scoped: await lint(${JSON.stringify(eager)}, root + '/main.ts', 'warn', [root]),
    outside: await lint(${JSON.stringify(eager)}, 'unrelated/main.js', 'warn', [root]),
    nonStartup: await lint("import.meta.glob('./components/*.ts', { eager: true });", 'helpers.ts', 'warn'),
};
process.stdout.write(JSON.stringify(result));
`;

type Finding = { rule: string; severity: number };

describe('extension loading performance preset', () => {
    let result: Record<string, Finding[]>;
    beforeAll(() => {
        result = JSON.parse(
            execFileSync(process.execPath, ['--input-type=module', '-e', probe], {
                cwd: path.resolve(__dirname, '../..'),
                encoding: 'utf8',
            }),
        ) as Record<string, Finding[]>;
    });

    it('keeps existing consumers opted out by default', () => {
        expect(result.defaults).toEqual([]);
    });

    it('reports both startup findings as warnings when enabled', () => {
        expect(result.warnings).toEqual([
            { rule: 'plugin-rules/no-eager-component-imports', severity: 1 },
            { rule: 'plugin-rules/no-eager-component-glob', severity: 1 },
        ]);
    });

    it('supports an explicit error policy', () => {
        expect(result.errors.map((finding) => finding.severity)).toEqual([2, 2]);
    });

    it('accepts deferred imports and unrelated shadowed component APIs', () => {
        expect(result.lazy).toEqual([]);
        expect(result.shadowed).toEqual([]);
    });

    it('checks native Vue scripts through the shared parser configuration', () => {
        expect(result.vue).toEqual([{ rule: 'plugin-rules/no-eager-component-imports', severity: 1 }]);
    });

    it('respects extension roots and limits eager-glob findings to startup files', () => {
        expect(result.scoped).toEqual(result.warnings);
        expect(result.outside).toEqual([]);
        expect(result.nonStartup).toEqual([]);
    });
});

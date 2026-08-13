/**
 * @sw-package framework
 *
 * Behavioural guard for the native-setup support the factory bakes into every
 * extension config. It lints real `.vue` fixtures through the generated config
 * and asserts what the config-shape spec cannot: a valid native-setup SFC lints
 * clean (which requires eslint-plugin-vue's internal `vue/base/setup-for-vue`
 * parser lookup to still resolve — the deep-resolution point that used to break
 * copied workspaces silently), and the two native-setup rules fire on a missing
 * marker and a non-kebab component filename. Both rule findings disappear if the
 * native-setup block is dropped, so this is a real regression guard for the wiring.
 *
 * The macro globals are not asserted here: ESLint's `no-undef` is inactive on
 * `.vue` in this AST-only subset, so the macros type-resolve on the `vue-tsc`
 * path instead — covered by `type-surface.spec` and the e2e specs. The type-aware
 * blocks are dropped from the config because they need a real TypeScript project
 * (the generated `.shopware/` bridge) that only exists in the e2e specs. The
 * factory is an .mjs module Jest cannot import directly, so one node subprocess
 * builds the config, runs ESLint, and serializes the rule ids per fixture.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

const withMarker =
    '<script setup>\nconst count = 1;\nswDefinePublic({ count });\n</script>\n<template><div>{{ count }}</div></template>\n';
const withoutMarker = '<script setup>\nconst count = 1;\n</script>\n<template><div>{{ count }}</div></template>\n';

const probeScript = `
import { ESLint } from 'eslint';

const { shopwareAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const config = shopwareAdminExtension({ tsconfigRootDir: process.cwd() }).filter(
    (block) =>
        block.name === 'shopware/admin-extension/native-setup' ||
        (block.name &&
            block.name.startsWith('shopware/admin-extension/vue-') &&
            block.name !== 'shopware/admin-extension/vue-typescript'),
);
const eslint = new ESLint({ overrideConfigFile: true, overrideConfig: config });

const cases = [
    ['good', ${JSON.stringify(withMarker)}, 'sw-my-widget.vue'],
    ['noMarker', ${JSON.stringify(withoutMarker)}, 'sw-widget.vue'],
    ['badName', ${JSON.stringify(withMarker)}, 'Bad_Name.vue'],
];
const result = {};
for (const [key, code, file] of cases) {
    const [report] = await eslint.lintText(code, { filePath: file });
    result[key] = report.messages.map((message) => message.ruleId ?? (message.fatal ? 'FATAL' : null));
}

process.stdout.write(JSON.stringify(result));
`;

describe('extension-tooling native-setup lint behaviour', () => {
    let result: Record<string, Array<string | null>>;

    beforeAll(() => {
        const output = execFileSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                probeScript,
            ],
            {
                cwd: path.resolve(__dirname, '../..'),
                encoding: 'utf8',
            },
        );

        result = JSON.parse(output) as Record<string, Array<string | null>>;
    });

    it('lints a valid native-setup .vue clean (the internal vue parser lookup still resolves)', () => {
        expect(result.good).toEqual([]);
    });

    it('reports a missing setup marker through sw-core-rules/valid-shopware-setup', () => {
        expect(result.noMarker).toContain('sw-core-rules/valid-shopware-setup');
    });

    it('reports a non-kebab component filename through sw-core-rules/native-setup-filename', () => {
        expect(result.badName).toContain('sw-core-rules/native-setup-filename');
    });
});

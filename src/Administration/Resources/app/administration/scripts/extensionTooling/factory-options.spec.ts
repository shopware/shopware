/**
 * @sw-package framework
 *
 * Guards the eslint factory's host-option contract: the defaults must
 * reproduce the extension behavior exactly (generated plugin configs pass only
 * tsconfigRootDir/extensionRoots), while each host option flips only its own
 * blocks. The factory is an .mjs module Jest cannot import directly, so one
 * node subprocess builds every variant and serializes the relevant block
 * facts.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

interface BlockSummary {
    name?: string;
    rules?: Record<string, string>;
    globals?: string[];
}

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

const probeScript = `
const variants = {
    defaults: {},
    typedSpecs: { specFiles: 'typed' },
    noSrcBoundary: { srcImportBoundary: false },
    splitSeverities: {
        internalApiSeverity: 'warn',
        deprecatedApiSeverity: 'off',
        templateDeprecationSeverity: 'error',
    },
    umbrella: { internalApiSeverity: 'warn' },
};

const { shopwareAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const summarize = (config) =>
    config.map((block) => ({
        name: block.name,
        rules: block.rules
            ? Object.fromEntries(
                  Object.entries(block.rules).map(([rule, entry]) => [rule, Array.isArray(entry) ? entry[0] : entry]),
              )
            : undefined,
        globals: block.languageOptions?.globals ? Object.keys(block.languageOptions.globals) : undefined,
    }));
const result = Object.fromEntries(
    Object.entries(variants).map(([key, options]) => [
        key,
        summarize(shopwareAdminExtension({ tsconfigRootDir: '/tmp', ...options })),
    ]),
);

process.stdout.write(JSON.stringify(result));
`;

describe('extension-tooling eslint factory host options', () => {
    let variants: Record<string, BlockSummary[]>;

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

        variants = JSON.parse(output) as Record<string, BlockSummary[]>;
    });

    function ruleSeverity(blocks: BlockSummary[], blockName: string, rule: string): string | undefined {
        return blocks.find((block) => block.name === blockName)?.rules?.[rule];
    }

    it('reproduces the extension behavior exactly with the extension-facing options alone', () => {
        const blocks = variants.defaults;

        expect(blocks.map((block) => block.name)).toContain('shopware/admin-extension/spec-files');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/runtime-contract', 'plugin-rules/no-src-imports')).toBe(
            'error',
        );
        expect(ruleSeverity(blocks, 'shopware/admin-extension/runtime-contract', 'no-restricted-imports')).toBe('error');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
        expect(
            ruleSeverity(
                blocks,
                'shopware/admin-extension/template-deprecations',
                'sw-deprecation-rules/no-deprecated-components',
            ),
        ).toBe('error');
    });

    it('bakes native-setup support into every extension config by default', () => {
        const nativeSetup = variants.defaults.find((block) => block.name === 'shopware/admin-extension/native-setup');

        expect(nativeSetup).toBeDefined();
        expect(nativeSetup?.rules?.['sw-core-rules/valid-shopware-setup']).toBe('error');
        expect(nativeSetup?.rules?.['sw-core-rules/native-setup-filename']).toBe('error');
        expect(nativeSetup?.globals).toEqual(
            expect.arrayContaining([
                'swDefinePublic',
                'swDefineOverride',
                'useSwPreviousState',
                'useSwProps',
                'useSwContext',
            ]),
        );
    });

    it('turns off only the no-unsafe rules on .vue while keeping the rest of the type-aware set', () => {
        const blocks = variants.defaults;

        // Without the Vue language service the project service types some Vue
        // surfaces as `any`, so the no-unsafe family reports false positives on
        // idiomatic Vue; a dedicated block placed after the type-aware one turns
        // just those five off (the `allowComponentTypeUnsafety` trade-off).
        for (const rule of [
            '@typescript-eslint/no-unsafe-argument',
            '@typescript-eslint/no-unsafe-assignment',
            '@typescript-eslint/no-unsafe-call',
            '@typescript-eslint/no-unsafe-member-access',
            '@typescript-eslint/no-unsafe-return',
        ]) {
            expect(ruleSeverity(blocks, 'shopware/admin-extension/vue-component-type-unsafety', rule)).toBe('off');
        }

        // Every other type-aware rule stays on: the resolvable ones keep working
        // on .vue (no-floating-promises via the type-aware block, no-deprecated
        // via the api-boundary block). No block disables the whole set on `.vue`.
        expect(
            ruleSeverity(blocks, 'shopware/admin-extension/vue-typescript', '@typescript-eslint/no-floating-promises'),
        ).toBe('error');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
        expect(blocks.map((block) => block.name)).not.toContain('shopware/admin-extension/vue-untyped');
    });

    it('keeps no-unused-vars on for .vue (the v10 parser links interpolation usage)', () => {
        const blocks = variants.defaults;

        // vue-eslint-parser 10 links `{{ }}` interpolations back to their
        // `<script setup>` bindings, so no `.vue`-scoped block disables
        // no-unused-vars: the only one (vue-component-type-unsafety) touches just
        // the no-unsafe family, leaving no-unused-vars from the type-aware block
        // in force.
        expect(blocks.map((block) => block.name)).not.toContain('shopware/admin-extension/vue-template-usage');
        expect(
            ruleSeverity(blocks, 'shopware/admin-extension/vue-typescript', '@typescript-eslint/no-unused-vars'),
        ).not.toBe('off');
        expect(
            ruleSeverity(blocks, 'shopware/admin-extension/vue-component-type-unsafety', 'no-unused-vars'),
        ).toBeUndefined();
        expect(
            ruleSeverity(
                blocks,
                'shopware/admin-extension/vue-component-type-unsafety',
                '@typescript-eslint/no-unused-vars',
            ),
        ).toBeUndefined();
    });

    it("omits the spec-files block entirely for specFiles: 'typed'", () => {
        const names = variants.typedSpecs.map((block) => block.name);

        expect(names).not.toContain('shopware/admin-extension/spec-files');
        // Everything else stays: the block count shrinks by exactly one.
        expect(variants.typedSpecs).toHaveLength(variants.defaults.length - 1);
    });

    it('flips only the two src-import rules for srcImportBoundary: false', () => {
        const blocks = variants.noSrcBoundary;

        // The block itself survives (it also carries globals and the plugin
        // registration) — only the rule severities flip.
        expect(blocks.map((block) => block.name)).toContain('shopware/admin-extension/runtime-contract');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/runtime-contract', 'plugin-rules/no-src-imports')).toBe('off');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/runtime-contract', 'no-restricted-imports')).toBe('off');
        expect(ruleSeverity(blocks, 'shopware/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
    });

    it('lets the deprecation severities diverge from the umbrella knob', () => {
        const blocks = variants.splitSeverities;

        expect(ruleSeverity(blocks, 'shopware/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'off',
        );
        expect(
            ruleSeverity(
                blocks,
                'shopware/admin-extension/template-deprecations',
                'sw-deprecation-rules/no-deprecated-component-usage',
            ),
        ).toBe('error');
    });

    it('keeps the umbrella knob driving both deprecation surfaces by default', () => {
        const blocks = variants.umbrella;

        expect(ruleSeverity(blocks, 'shopware/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'warn',
        );
        expect(
            ruleSeverity(
                blocks,
                'shopware/admin-extension/template-deprecations',
                'sw-deprecation-rules/no-deprecated-components',
            ),
        ).toBe('warn');
    });
});

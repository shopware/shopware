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

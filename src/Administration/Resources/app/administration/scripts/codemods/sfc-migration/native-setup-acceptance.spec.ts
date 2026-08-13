import fs from 'node:fs';
import path from 'node:path';
import { Linter } from 'eslint';
// Namespace imports: both modules are CommonJS and expose their API as the bare
// exports object, without an interop default.
import * as vueParser from 'vue-eslint-parser';
import * as validShopwareSetup from '../../../eslint-rules/core-rules/valid-shopware-setup';
import { transformShopwareSetupSfc } from '../../../build/vue-setup-transform';
import { mergeComponentFiles } from './generate-sfc';

const fixturesDir = path.join(__dirname, '__fixtures__');

/**
 * Acceptance tests for the generated output.
 *
 * The codemod is only useful if what it writes actually builds, so every fixture
 * is pushed through the real build-time transform (the same module Vite, Jest,
 * ESLint, and Volar use) and through the `valid-shopware-setup` ESLint rule that
 * mirrors it into the editor. A test here failing means the codemod would write
 * a `.vue` file the Administration cannot compile.
 */
const fixtureNames = fs
    .readdirSync(fixturesDir)
    .filter((entry) => entry.endsWith('.index.js'))
    .map((entry) => entry.replace(/\.index\.js$/, ''))
    .sort();

function readComponentSources(name: string): { twig: string; js: string } {
    const twigPath = path.join(fixturesDir, `${name}.html.twig`);

    return {
        // Fixtures that only exercise script blockers ship no template.
        twig: fs.existsSync(twigPath) ? fs.readFileSync(twigPath, 'utf8') : `<div class="${name}"></div>`,
        js: fs.readFileSync(path.join(fixturesDir, `${name}.index.js`), 'utf8'),
    };
}

function lintGeneratedSfc(sfc: string, fileName: string): Linter.LintMessage[] {
    return new Linter().verify(
        sfc,
        {
            files: ['**/*.vue'],
            languageOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                parser: vueParser as Linter.Parser,
            },
            plugins: {
                'sw-core-rules': { rules: { 'valid-shopware-setup': validShopwareSetup } },
            },
            rules: { 'sw-core-rules/valid-shopware-setup': 'error' },
        },
        fileName,
    );
}

describe('scripts/codemods/sfc-migration native setup acceptance', () => {
    it('covers every component fixture', () => {
        expect(fixtureNames.length).toBeGreaterThan(0);
    });

    // Guards the harness itself: a lint setup that reports nothing would make
    // every assertion below pass regardless of what the codemod emits.
    it('reports a violation for an SFC without the mandatory marker', () => {
        const messages = lintGeneratedSfc('<script setup>\nconst count = 1;\n</script>', 'sw-no-marker.vue');

        expect(messages).toHaveLength(1);
        expect(messages[0].message).toContain('swDefinePublic');
    });

    describe.each(fixtureNames)('%s', (name) => {
        const { twig, js } = readComponentSources(name);
        const result = mergeComponentFiles(twig, js);
        const fileName = `${result.componentName}.vue`;

        if (result.status === 'not-migratable') {
            it('writes nothing and reports at least one blocker', () => {
                expect(result.sfc).toBe('');
                expect(result.blockers.length).toBeGreaterThan(0);
            });

            return;
        }

        it('is accepted by the build-time native setup transform', () => {
            expect(() => transformShopwareSetupSfc(result.sfc, fileName)).not.toThrow();
        });

        it('is lowered into the extension runtime with the compile-time marker consumed', () => {
            const transformed = transformShopwareSetupSfc(result.sfc, fileName);

            expect(transformed?.code).toContain('Shopware.Component.attachOverrides(');
            expect(transformed?.code).toContain(`name: '${result.componentName}'`);
            expect(transformed?.code).not.toContain('swDefinePublic');
        });

        it('passes the valid-shopware-setup ESLint rule', () => {
            expect(lintGeneratedSfc(result.sfc, fileName)).toEqual([]);
        });
    });
});

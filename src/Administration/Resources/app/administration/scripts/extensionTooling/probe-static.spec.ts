/**
 * @sw-package framework
 *
 * The static verdicts: which extension-owned configs count as composing the
 * bridge, and the `why` text for the ones that do not.
 */

import path from 'path';
import { eslintConfigVerdict, tsconfigVerdict } from './probe-static';
import { cleanupTempProject, createTempProject, writeFile } from './test-helpers';

describe('scripts/extensionTooling/probe-static', () => {
    let projectRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-probe-');
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function verdictFor(relative: string): ReturnType<typeof tsconfigVerdict> {
        return tsconfigVerdict(path.join(projectRoot, relative), relative);
    }

    describe('tsconfigVerdict', () => {
        it('accepts a config that extends the bridge, JSONC comments included', () => {
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), [
                '{',
                '    // JSONC comments must parse',
                '    "extends": "./.shopware/tsconfig.json",',
                '    "include": ["src/**/*"]',
                '}',
            ]);

            expect(verdictFor('admin/tsconfig.json')).toEqual({ path: 'admin/tsconfig.json', composes: true });
        });

        it('follows an extends chain through an own base config to the shipped preset', () => {
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), ['{ "extends": "./base/middle.json" }']);
            writeFile(path.join(projectRoot, 'admin/base/middle.json'), [
                '{ "extends": "../../extension-tooling/tsconfig.base.json" }',
            ]);
            writeFile(path.join(projectRoot, 'extension-tooling/tsconfig.base.json'), ['{}']);

            expect(verdictFor('admin/tsconfig.json').composes).toBe(true);
        });

        it('rejects a config whose chain never reaches the preset', () => {
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), ['{ "compilerOptions": { "strict": true } }']);

            const verdict = verdictFor('admin/tsconfig.json');

            expect(verdict.composes).toBe(false);
            expect(verdict.detail).toContain('does not reach the Shopware preset');
            expect(verdict.reason).toBe('extends-missing');
        });

        it('explains the files-override trap and points path declarers at tsconfig.aliases.json', () => {
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), [
                '{',
                '    "extends": "./.shopware/tsconfig.json",',
                '    "files": ["x.d.ts"],',
                '    "compilerOptions": { "paths": { "MyPlugin/*": ["src/*"] } }',
                '}',
            ]);

            const verdict = verdictFor('admin/tsconfig.json');

            expect(verdict.composes).toBe(false);
            expect(verdict.detail).toContain('"files"');
            expect(verdict.detail).toContain('tsconfig.aliases.json');
            // Distinct from a missing extends: this config has one, so the
            // remediation derived from the reason must not ask for it again.
            expect(verdict.reason).toBe('files-override');
        });

        it('rejects a config that inherits the bridge "files" without declaring an own "include"', () => {
            writeFile(path.join(projectRoot, 'admin/.shopware/tsconfig.json'), [
                '{ "files": ["../../extension-tooling/admin-types.d.ts"] }',
            ]);
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), ['{ "extends": "./.shopware/tsconfig.json" }']);

            const verdict = verdictFor('admin/tsconfig.json');

            expect(verdict.composes).toBe(false);
            expect(verdict.detail).toContain('declares no "include"');
            expect(verdict.reason).toBe('include-missing');
        });

        it('accepts an "include" inherited from the plugin\'s own base config', () => {
            writeFile(path.join(projectRoot, 'admin/.shopware/tsconfig.json'), ['{ "files": ["x.d.ts"] }']);
            writeFile(path.join(projectRoot, 'admin/base.json'), [
                '{ "extends": "./.shopware/tsconfig.json", "include": ["src/**/*"] }',
            ]);
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), ['{ "extends": "./base.json" }']);

            expect(verdictFor('admin/tsconfig.json').composes).toBe(true);
        });

        it('does not demand an "include" when no "files" suppresses the default glob', () => {
            // Extending the shipped preset directly inherits no "files", so
            // TypeScript's default "**/*" still covers the sources.
            writeFile(path.join(projectRoot, 'extension-tooling/tsconfig.base.json'), ['{}']);
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), [
                '{ "extends": "../extension-tooling/tsconfig.base.json" }',
            ]);

            expect(verdictFor('admin/tsconfig.json').composes).toBe(true);
        });

        it('surfaces a parse error as the reason', () => {
            writeFile(path.join(projectRoot, 'admin/tsconfig.json'), ['{ "extends": ']);

            const verdict = verdictFor('admin/tsconfig.json');

            expect(verdict.composes).toBe(false);
            expect(verdict.detail).toBeTruthy();
        });
    });

    describe('eslintConfigVerdict', () => {
        it('accepts bridge and factory imports, rejects an unrelated config', () => {
            const cases = {
                'bridge.mjs': ["import shopware from './.shopware/eslint.mjs';"],
                'factory.mjs': ["import { shopwareAdminExtension } from '../extension-tooling/eslint.mjs';"],
                'own.mjs': ['export default [];'],
            };

            for (const [
                file,
                lines,
            ] of Object.entries(cases)) {
                writeFile(path.join(projectRoot, file), lines);
            }

            expect(eslintConfigVerdict(path.join(projectRoot, 'bridge.mjs'), 'bridge.mjs').composes).toBe(true);
            expect(eslintConfigVerdict(path.join(projectRoot, 'factory.mjs'), 'factory.mjs').composes).toBe(true);

            const own = eslintConfigVerdict(path.join(projectRoot, 'own.mjs'), 'own.mjs');

            expect(own.composes).toBe(false);
            expect(own.detail).toContain('does not compose the Shopware factory');
        });
    });
});

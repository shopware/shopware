/**
 * @sw-package framework
 *
 * End-to-end proof that spec files are type-checked by the dedicated spec
 * program: jest globals resolve (no "Cannot find name 'describe'") while a real
 * type error inside a spec is caught and fails the check.
 */

import path from 'path';
import { checkExtensions } from '../check';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 180000;

describe('extension tooling typed spec files (e2e)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-typed-specs-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        writeFile(path.join(projectRoot, 'custom/plugins/SpecPlugin/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/SpecPlugin/src/Resources/app/administration/src/main.ts'), [
            'export const value: number = 1;',
        ]);
        // A spec that uses jest globals (which must resolve) and contains a
        // genuine type error (caught by the dedicated spec program).
        writeFile(path.join(projectRoot, 'custom/plugins/SpecPlugin/src/Resources/app/administration/src/main.spec.ts'), [
            "describe('SpecPlugin', () => {",
            "    it('has a type error', () => {",
            "        const typed: number = 'not a number';",
            '        expect(typed).toBe(typed);',
            '    });',
            '});',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'SpecPlugin',
                basePath: 'custom/plugins/SpecPlugin/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
    }, CHECK_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'resolves jest globals and catches a type error in a spec',
        async () => {
            const check = await checkExtensions({ projectRoot, administrationRoot, only: 'SpecPlugin' });
            const specs = check.results[0].typescriptSpecs;

            // The runtime program is clean; the type error is only in the spec.
            expect(check.results[0].typescript.status).toBe('passed');
            expect(specs.status).toBe('failed');
            expect(specs.output).toContain('TS2322');
            expect(specs.output).toContain('main.spec.ts');
            // jest globals are typed, so `describe` is not an unknown name.
            expect(specs.output).not.toContain("Cannot find name 'describe'");
            expect(check.exitCode).toBe(1);
        },
        CHECK_TIMEOUT,
    );

    it(
        'lints spec files with jest globals and never rejects them as outside a project',
        async () => {
            const check = await checkExtensions({ projectRoot, administrationRoot, only: 'SpecPlugin' });
            const eslint = check.results[0].eslint;

            // Specs are parsed standalone with the jest globals available (the
            // untyped spec block), so ESLint neither flags `describe` as an
            // undefined global nor rejects the spec as outside every project.
            expect(eslint.status).toBe('passed');
            expect(eslint.output).not.toContain('not found in any of the provided project');
            expect(eslint.output).not.toContain("'describe' is not defined");
        },
        CHECK_TIMEOUT,
    );
});

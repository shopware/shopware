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
        // A spec that uses jest globals (must resolve), contains a genuine type
        // error (caught by the spec program), and a floating promise (only a
        // type-aware ESLint rule catches it — proving type-aware spec linting).
        writeFile(path.join(projectRoot, 'custom/plugins/SpecPlugin/src/Resources/app/administration/src/main.spec.ts'), [
            "describe('SpecPlugin', () => {",
            "    it('has a type error', () => {",
            "        const typed: number = 'not a number';",
            '        Promise.resolve();',
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
        'type-aware-lints managed spec files without a not-in-project error',
        async () => {
            const check = await checkExtensions({ projectRoot, administrationRoot, only: 'SpecPlugin' });
            const { output } = check.results[0].eslint;

            // A type-aware-only rule fired on the spec, so the spec program was
            // discovered — and it was not rejected as outside every project.
            expect(output).toContain('no-floating-promises');
            expect(output).not.toContain('not found in any of the provided project');
        },
        CHECK_TIMEOUT,
    );
});

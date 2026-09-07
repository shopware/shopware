/**
 * @sw-package framework
 *
 * The generated spec tsconfig inside the `.shopware/` bridge: a companion
 * type-check program that composes the runtime bridge, swaps in jest types, and
 * includes only the spec files the runtime program excludes.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { specTsconfigPath } from '../check-run';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

describe('scripts/extensionTooling/setup spec tsconfig', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-specs-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
        writeFile(path.join(projectRoot, 'custom/plugins/Specced/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Specced/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Specced',
                basePath: 'custom/plugins/Specced/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function readSpecTsconfig(): {
        extends: string;
        compilerOptions?: { typeRoots?: string[] };
        files: string[];
        include: string[];
        exclude?: string[];
    } {
        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const target = result.manifest.projects[0].targets[0];
        const raw = fs.readFileSync(path.join(projectRoot, specTsconfigPath(target)), 'utf8');

        return JSON.parse(raw.split('\n').slice(1).join('\n')) as ReturnType<typeof readSpecTsconfig>;
    }

    it('composes the runtime bridge, injects jest types, and includes only specs', () => {
        const specTsconfig = readSpecTsconfig();

        // It extends the runtime bridge tsconfig beside it — inheriting the host
        // paths and plugin aliases — rather than the base preset directly.
        expect(specTsconfig.extends).toBe('./tsconfig.json');
        // typeRoots points at the Administration's own @types so the jest
        // triple-slash reference in spec-types.d.ts resolves.
        expect(specTsconfig.compilerOptions?.typeRoots?.some((root) => root.endsWith('node_modules/@types'))).toBe(true);
        expect(specTsconfig.files.some((file) => file.endsWith('admin-types.d.ts'))).toBe(true);
        expect(specTsconfig.files.some((file) => file.endsWith('spec-types.d.ts'))).toBe(true);
        // Only specs are pulled in — no runtime globs and no spec exclusion.
        expect(specTsconfig.include.length).toBeGreaterThan(0);
        expect(specTsconfig.include.every((glob) => /\*\.spec\.(ts|tsx|js)$/.test(glob))).toBe(true);
        expect(specTsconfig.exclude).toBeUndefined();
    });
});

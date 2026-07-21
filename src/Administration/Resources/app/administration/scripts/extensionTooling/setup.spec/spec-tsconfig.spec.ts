/**
 * @sw-package framework
 *
 * The generated spec leaf tsconfig: a companion type-check program that adds
 * jest types and includes only the spec files the runtime program excludes.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

describe('scripts/extensionTooling/setup spec leaf tsconfig', () => {
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

    function readSpecLeaf(): { extends: string; files: string[]; include: string[]; exclude?: string[] } {
        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const target = result.manifest.projects[0].targets[0];
        const raw = fs.readFileSync(path.join(projectRoot, target.specTsconfig), 'utf8');

        return JSON.parse(raw.split('\n').slice(1).join('\n')) as {
            extends: string;
            files: string[];
            include: string[];
            exclude?: string[];
        };
    }

    it('injects the admin type surface plus jest types and includes only specs', () => {
        const specLeaf = readSpecLeaf();

        expect(specLeaf.extends).toMatch(/tsconfig\.base\.json$/);
        expect(specLeaf.files.some((file) => file.endsWith('admin-types.d.ts'))).toBe(true);
        expect(specLeaf.files.some((file) => file.endsWith('spec-types.d.ts'))).toBe(true);
        expect(specLeaf.include).toEqual(
            expect.arrayContaining([expect.stringMatching(/custom\/plugins\/Specced\/.*\*\*\/\*\.spec\.ts$/)]),
        );
        // Only specs are pulled in — no runtime globs and no spec exclusion.
        expect(specLeaf.include.every((glob) => /\*\.spec\.(ts|tsx|js)$/.test(glob))).toBe(true);
        expect(specLeaf.exclude).toBeUndefined();
    });
});

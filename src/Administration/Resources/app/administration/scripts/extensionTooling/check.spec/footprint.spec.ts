/**
 * @sw-package framework
 *
 * Integration: what the runner is allowed to touch. Two runs must produce the
 * same generated layout, and everything outside `var/` must survive untouched —
 * with one documented exception, the git-ignored entity-schema stub inside the
 * Administration's own package.
 */

import fs from 'fs';
import path from 'path';
import { discoverAdminRoots } from '../discovery';
import { ensureEntitySchema, resetToolingDirectory, runCheck } from '../runner';
import {
    createExtension,
    createStubAdministration,
    createTempProject,
    removeTempProject,
    snapshotTree,
    writeBundleConfig,
} from '../test-helpers';

jest.setTimeout(300_000);

describe('scripts/extensionTooling footprint (integration)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject();
        administrationRoot = createStubAdministration(projectRoot);

        const extension = createExtension(projectRoot, {
            name: 'SwagFootprint',
            sources: { 'main.ts': 'export const label: string = Shopware.name;\n' },
        });

        writeBundleConfig(projectRoot, {
            SwagFootprint: { basePath: extension.basePath, technicalName: 'swag-footprint' },
        });
    });

    afterEach(() => {
        removeTempProject(projectRoot);
    });

    const check = (): void => {
        runCheck({
            projectRoot,
            administrationRoot,
            roots: discoverAdminRoots({
                projectRoot,
                administrationRoot,
                pluginsConfigPath: path.join(projectRoot, 'var', 'plugins.json'),
            }),
            types: true,
            lint: true,
            fix: false,
        });
    };

    it('is idempotent: a second run produces the same generated layout', () => {
        check();

        const first = snapshotTree(path.join(projectRoot, 'var/admin-extension-tooling'));

        check();

        expect(snapshotTree(path.join(projectRoot, 'var/admin-extension-tooling'))).toEqual(first);
    });

    it('leaves everything outside var/ untouched apart from the entity-schema stub', () => {
        // The stub is written on the first run; taking the baseline after it keeps the
        // assertion about *further* writes rather than about the documented exception.
        ensureEntitySchema(administrationRoot);

        const before = snapshotTree(projectRoot, ['var']);

        check();
        check();

        expect(snapshotTree(projectRoot, ['var'])).toEqual(before);
    });

    it('writes the entity-schema stub only when the Administration has none', () => {
        const schemaPath = path.join(administrationRoot, 'src/entity-schema-definition.d.ts');

        expect(fs.existsSync(schemaPath)).toBe(false);

        check();

        expect(fs.readFileSync(schemaPath, 'utf8')).toContain('interface Entities {}');

        fs.writeFileSync(schemaPath, '// generated for real\n', 'utf8');
        check();

        expect(fs.readFileSync(schemaPath, 'utf8')).toBe('// generated for real\n');
    });

    it('replaces the generated directory instead of merging it, so orphans are impossible', () => {
        const toolingDir = path.join(projectRoot, 'var/admin-extension-tooling');

        fs.mkdirSync(path.join(toolingDir, 'gone-extension'), { recursive: true });
        fs.writeFileSync(path.join(toolingDir, 'gone-extension/tsconfig.json'), '{}', 'utf8');

        check();

        expect(fs.readdirSync(toolingDir)).toEqual(['swag-footprint']);
    });

    it('keeps the generated directory inside var/', () => {
        expect(resetToolingDirectory(projectRoot)).toBe(path.join(projectRoot, 'var/admin-extension-tooling'));
        expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling'))).toBe(true);
    });
});

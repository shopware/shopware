/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions, countEslintFindings, countTypeScriptFindings, runPool } from './check';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from './test-helpers';

function linkRealToolchain(administrationRoot: string): void {
    fs.rmSync(path.join(administrationRoot, 'node_modules'), { recursive: true, force: true });
    fs.symlinkSync(path.join(realAdministrationRoot, 'node_modules'), path.join(administrationRoot, 'node_modules'), 'dir');
}

describe('scripts/extensionTooling/check', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-check-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('bounds parallelism while preserving job order', async () => {
        let running = 0;
        let maxRunning = 0;
        const jobs = Array.from({ length: 9 }, (_, jobIndex) => async () => {
            running += 1;
            maxRunning = Math.max(maxRunning, running);
            await new Promise((resolve) => {
                setTimeout(resolve, 10);
            });
            running -= 1;

            return jobIndex;
        });

        const results = await runPool(jobs, 3);

        expect(results).toEqual([
            0,
            1,
            2,
            3,
            4,
            5,
            6,
            7,
            8,
        ]);
        expect(maxRunning).toBeLessThanOrEqual(3);
    });

    it('counts findings from native tool output without altering it', () => {
        const typescriptOutput = [
            "src/main.ts(4,7): error TS2322: Type 'string' is not assignable to type 'number'.",
            'src/App.vue:8:3 - error TS2339: Property missing does not exist.',
            'unrelated line',
        ].join('\n');
        const eslintOutput = [
            '  4:7 error Unexpected console statement no-console',
            '✖ 3 problems (2 errors, 1 warning)',
        ].join('\n');

        expect(countTypeScriptFindings(typescriptOutput)).toBe(2);
        expect(countEslintFindings(eslintOutput)).toBe(3);
        expect(countEslintFindings('clean')).toBe(0);
    });

    it('passes on an empty extension set', async () => {
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.results).toEqual([]);
        expect(check.exitCode).toBe(0);
        expect(check.fatalDiagnostics).toEqual([]);
    });

    it('hard-fails with the exact fix command when the entity schema is missing', async () => {
        fs.rmSync(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'));
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('composer admin:generate-entity-schema-types');
    });

    it('hard-fails when a root config is user-owned', async () => {
        writePluginsConfig(projectRoot, []);
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{}}\n');

        const check = await checkExtensions({ projectRoot, administrationRoot });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('root tsconfig.json is user-owned');
    });

    it('hard-fails when --only matches no extension', async () => {
        writePluginsConfig(projectRoot, []);

        const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Nope' });

        expect(check.exitCode).toBe(1);
        expect(check.fatalDiagnostics.join('\n')).toContain('--only=Nope');
    });

    it('visibly skips custom configs that do not compose the Shopware preset', async () => {
        linkRealToolchain(administrationRoot);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
            '{',
            '    "compilerOptions": { "strict": true, "noEmit": true },',
            '    "include": ["src/**/*.ts"]',
            '}',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/eslint.config.mjs'), [
            'export default [{ rules: {} }];',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Probe',
                basePath: 'custom/plugins/Probe/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const check = await checkExtensions({ projectRoot, administrationRoot });
        const probe = check.results[0];

        expect(check.results).toHaveLength(1);
        expect(probe.tsMode).toBe('unmanaged');
        expect(probe.eslintMode).toBe('unmanaged');
        expect(probe.typescript.status).toBe('unmanaged');
        expect(probe.eslint.status).toBe('unmanaged');
        expect(check.exitCode).toBe(0);
    }, 60000);
});

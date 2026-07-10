/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import {
    compareWithBaselines,
    parseEslintDiagnostics,
    parseTypeScriptDiagnostics,
    writeBaseline,
    type ToolingDiagnostic,
} from './check';
import type { ExtensionToolingProject } from './setup';

describe('Administration extension tooling checks', () => {
    let projectRoot: string;
    let project: ExtensionToolingProject;

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-admin-tooling-check-'));
        const sourcePath = 'custom/plugins/Example/src/Resources/app/administration/src';

        fs.mkdirSync(path.join(projectRoot, sourcePath), { recursive: true });
        project = {
            technicalName: 'Example',
            basePath: 'custom/plugins/Example',
            sourcePath,
            tsconfig: null,
            checkTsconfig: 'var/admin-extension-tooling/projects/example.json',
            eslintConfig: null,
            baseline: 'custom/plugins/Example/.shopware-admin-baseline.json',
            mode: 'default',
        };
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('parses TypeScript and ESLint output into stable diagnostics', () => {
        const typescriptOutput = [
            'custom/plugins/Example/src/Resources/app/administration/src/main.ts(4,7): error TS2322: Type string is not assignable to type number.',
            'custom/plugins/Example/src/Resources/app/administration/src/App.vue:8:3 - error TS2339: Property missing does not exist.',
        ].join('\n');
        const eslintOutput = JSON.stringify([
            {
                filePath: path.join(projectRoot, project.sourcePath, 'main.ts'),
                messages: [
                    {
                        line: 3,
                        column: 1,
                        ruleId: '@typescript-eslint/no-unsafe-assignment',
                        message: 'Unsafe assignment.',
                    },
                ],
            },
        ]);

        expect(parseTypeScriptDiagnostics(typescriptOutput, projectRoot)).toHaveLength(2);
        expect(parseEslintDiagnostics(eslintOutput, projectRoot)).toEqual([
            expect.objectContaining({
                tool: 'eslint',
                file: project.sourcePath + '/main.ts',
                code: '@typescript-eslint/no-unsafe-assignment',
            }),
        ]);
    });

    it('suppresses only exact migrated diagnostics and exposes stale baseline entries', () => {
        const diagnostic: ToolingDiagnostic = {
            tool: 'typescript',
            file: project.sourcePath + '/main.ts',
            line: 4,
            column: 7,
            code: 'TS2322',
            message: 'Type string is not assignable to type number.',
        };

        expect(compareWithBaselines(projectRoot, [project], [diagnostic])).toMatchObject({
            newDiagnostics: [diagnostic],
            baselinedDiagnostics: [],
            staleDiagnostics: [],
        });

        writeBaseline(projectRoot, project, [diagnostic]);

        expect(compareWithBaselines(projectRoot, [project], [diagnostic])).toMatchObject({
            newDiagnostics: [],
            baselinedDiagnostics: [diagnostic],
            staleDiagnostics: [],
        });

        const movedDiagnostic = {
            ...diagnostic,
            line: 5,
        };
        const comparison = compareWithBaselines(projectRoot, [project], [movedDiagnostic]);

        expect(comparison.newDiagnostics).toEqual([movedDiagnostic]);
        expect(comparison.baselinedDiagnostics).toEqual([]);
        expect(comparison.staleDiagnostics).toHaveLength(1);
    });
});

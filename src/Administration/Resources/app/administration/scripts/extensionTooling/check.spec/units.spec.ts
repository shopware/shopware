/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import {
    appendFixHint,
    buildEslintArguments,
    buildVueTscArguments,
    countEslintFindings,
    countTypeCheckableFiles,
    countTypeScriptFindings,
    relativizeToolOutput,
    runPool,
} from '../check';
import { cleanupTempProject, createTempProject, writeFile } from '../test-helpers';

describe('scripts/extensionTooling/check units', () => {
    let projectRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-check-');
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

    it('threads --fix to ESLint only and appends the toolchain fix hint', () => {
        expect(buildEslintArguments('/bin/eslint.js', [], ['custom/plugins/X/src'], true)).toContain('--fix');
        expect(buildEslintArguments('/bin/eslint.js', [], ['custom/plugins/X/src'], false)).not.toContain('--fix');
        expect(buildVueTscArguments('/bin/vue-tsc.js', '/tsconfig.json')).not.toContain('--fix');

        const fixable =
            '✖ 3 problems (3 errors, 0 warnings)\n  3 errors and 0 warnings potentially fixable with the `--fix` option.';

        expect(appendFixHint(fixable, 'MyPlugin')).toContain(
            'auto-fixable: composer admin:check-extensions -- --only=MyPlugin --fix',
        );
        expect(appendFixHint('✖ 1 problem (1 error, 0 warnings)', 'MyPlugin')).not.toContain('auto-fixable');
    });

    it('strips the project root from tool output, including its canonicalized form', () => {
        const canonicalRoot = fs.realpathSync(projectRoot);
        const output = [
            `${projectRoot}/custom/plugins/X/src/main.ts  1:1  error  nope`,
            `${canonicalRoot}/custom/plugins/X/src/other.ts  2:2  error  nope`,
        ].join('\n');

        const relativized = relativizeToolOutput(output, projectRoot);

        expect(relativized).toContain('custom/plugins/X/src/main.ts');
        expect(relativized).toContain('custom/plugins/X/src/other.ts');
        expect(relativized).not.toContain(canonicalRoot);
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

    it('counts only files vue-tsc would actually type-check', () => {
        writeFile(path.join(projectRoot, 'plugin/src/main.js'), ['export default {};']);
        writeFile(path.join(projectRoot, 'plugin/src/helper.spec.ts'), ['export {};']);

        expect(countTypeCheckableFiles(projectRoot, ['plugin/src'])).toBe(0);

        writeFile(path.join(projectRoot, 'plugin/src/component.vue'), ['<template><div /></template>']);
        writeFile(path.join(projectRoot, 'plugin/src/typed.ts'), ['export {};']);

        expect(countTypeCheckableFiles(projectRoot, ['plugin/src'])).toBe(2);
        expect(countTypeCheckableFiles(projectRoot, ['does/not/exist'])).toBe(0);
    });
});

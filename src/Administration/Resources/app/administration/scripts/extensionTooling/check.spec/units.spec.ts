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
    parseEslintFindings,
    parseTypeScriptFindings,
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
        // Ambient declaration files carry no checkable source and are resolved by
        // TypeScript on its own terms — they must not count as covered source.
        writeFile(path.join(projectRoot, 'plugin/src/type/types.d.ts'), ['export {};']);

        expect(countTypeCheckableFiles(projectRoot, ['plugin/src'])).toBe(0);

        writeFile(path.join(projectRoot, 'plugin/src/component.vue'), ['<template><div /></template>']);
        writeFile(path.join(projectRoot, 'plugin/src/typed.ts'), ['export {};']);

        // Still 2: the .d.ts is excluded even though it ends in ".ts".
        expect(countTypeCheckableFiles(projectRoot, ['plugin/src'])).toBe(2);
        expect(countTypeCheckableFiles(projectRoot, ['does/not/exist'])).toBe(0);
    });

    it('parses TypeScript findings into structured entries without line numbers', () => {
        const output = [
            "src/main.ts(4,7): error TS2322: Type 'string' is not assignable to type 'number'.",
            'src/App.vue:8:3 - error TS2339: Property missing does not exist.',
            "  src/main.ts(1,1): 'other' is declared here.",
            'unrelated line',
        ].join('\n');

        const findings = parseTypeScriptFindings(output);

        expect(findings).toEqual([
            { file: 'src/main.ts', code: 'TS2322', message: "Type 'string' is not assignable to type 'number'." },
            { file: 'src/App.vue', code: 'TS2339', message: 'Property missing does not exist.' },
        ]);
        // The related-information line is ignored, so the structured count
        // matches the regex counter (the baseline exit-code safety net).
        expect(findings).toHaveLength(countTypeScriptFindings(output));
    });

    it('parses ESLint findings and keeps both severities for the count invariant', () => {
        const output = [
            'custom/plugins/X/src/main.ts',
            '  4:7   error    Unexpected console statement  no-console',
            '  9:1   warning  Missing return type           @typescript-eslint/explicit-function-return-type',
            'custom/plugins/X/src/other.ts',
            '  1:1   error    Parsing error: Unexpected token',
            '',
            '✖ 3 problems (2 errors, 1 warning)',
        ].join('\n');

        const findings = parseEslintFindings(output);

        expect(findings).toEqual([
            {
                file: 'custom/plugins/X/src/main.ts',
                rule: 'no-console',
                message: 'Unexpected console statement',
                severity: 'error',
            },
            {
                file: 'custom/plugins/X/src/main.ts',
                rule: '@typescript-eslint/explicit-function-return-type',
                message: 'Missing return type',
                severity: 'warning',
            },
            {
                file: 'custom/plugins/X/src/other.ts',
                rule: '',
                message: 'Parsing error: Unexpected token',
                severity: 'error',
            },
        ]);
        // Both severities are parsed so the total matches the summary counter.
        expect(findings).toHaveLength(countEslintFindings(output));
    });

    it('attributes multi-line ESLint messages to the right file and rule', () => {
        // Rules like @typescript-eslint/unbound-method emit a message with an
        // embedded newline; ESLint prints the continuation line un-indented,
        // carrying the rule id at its end.
        const output = [
            'custom/plugins/X/src/foo.ts',
            '  10:5  error  Avoid referencing unbound methods which may cause unintentional scoping.',
            'If a function does not access this, it can be annotated  @typescript-eslint/unbound-method',
            '  12:3  error  Unexpected any  @typescript-eslint/no-explicit-any',
            '',
            '✖ 2 problems (2 errors, 0 warnings)',
        ].join('\n');

        const findings = parseEslintFindings(output);

        expect(findings).toHaveLength(2);
        // The un-indented continuation is not mistaken for a file header: the
        // second finding keeps the real file, not the message text.
        expect(findings.every((finding) => finding.file === 'custom/plugins/X/src/foo.ts')).toBe(true);
        // The rule id printed on the continuation line is attributed to its finding.
        expect(findings[0].rule).toBe('@typescript-eslint/unbound-method');
        expect(findings[1].rule).toBe('@typescript-eslint/no-explicit-any');
        expect(findings).toHaveLength(countEslintFindings(output));
    });
});

/**
 * @sw-package framework
 *
 * Lints real files of a temp extension through the factory config, so the
 * type-aware rules resolve against an actual TypeScript program. The factory is
 * an .mjs module Jest cannot import directly, so one node subprocess builds the
 * config, runs ESLint, and serializes the rule ids per file.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';
import { cleanupTempProject, createTempProject, writeFile } from './test-helpers';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

function probeScript(projectRoot: string): string {
    return `
import { ESLint } from 'eslint';

const projectRoot = ${JSON.stringify(projectRoot)};
const { shopwareAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const eslint = new ESLint({
    cwd: projectRoot,
    overrideConfigFile: true,
    overrideConfig: shopwareAdminExtension({ tsconfigRootDir: projectRoot, legacyTwig: false }),
});

const result = {};
for (const report of await eslint.lintFiles(['src'])) {
    result[report.filePath.slice(projectRoot.length + 1)] = report.messages.map(
        (message) => message.ruleId ?? (message.fatal ? 'FATAL' : null),
    );
}

process.stdout.write(JSON.stringify(result));
`;
}

describe('extension-tooling code-quality lint behaviour', () => {
    let projectRoot: string;
    let result: Record<string, Array<string | null>>;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-code-quality-');

        writeFile(path.join(projectRoot, 'tsconfig.json'), [
            '{',
            '    "compilerOptions": { "strict": true, "target": "ESNext", "module": "ESNext", "moduleResolution": "Bundler", "noEmit": true, "types": [] },',
            '    "include": ["src/**/*.ts"]',
            '}',
        ]);
        writeFile(path.join(projectRoot, 'src/legacy.js'), [
            'export function pick(value, fallback) {',
            '    if (value) {',
            '        return value;',
            '    } else {',
            '        return fallback;',
            '    }',
            '}',
            '',
            'export function log(value) {',
            '    console.info(value);',
            '    return;',
            '}',
            '',
            'export function choose(first, second) {',
            '    if (first) {',
            '        console.info(first);',
            '    } else {',
            '        if (second) {',
            '            console.info(second);',
            '        }',
            '    }',
            '}',
            '',
            'export const isSet = (value) => (value ? true : false);',
        ]);
        writeFile(path.join(projectRoot, 'src/typed.ts'), [
            'export function isOn(flag: boolean): boolean {',
            '    return flag === true;',
            '}',
            '',
            'export function label(name: string): string {',
            '    return `${name}`;',
            '}',
            '',
            'export function asText(name: string): string {',
            '    return String(name);',
            '}',
        ]);
        writeFile(path.join(projectRoot, 'src/typed.spec.ts'), [
            "describe('isOn', () => {",
            "    it('compares a boolean literal', () => {",
            '        const flag = Math.random() > 0.5;',
            '        expect(flag === true).toBe(flag);',
            '    });',
            '});',
        ]);

        const output = execFileSync(process.execPath, ['--input-type=module', '-e', probeScript(projectRoot)], {
            cwd: path.resolve(__dirname, '../..'),
            encoding: 'utf8',
        });

        result = JSON.parse(output) as Record<string, Array<string | null>>;
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it('reports redundant control flow in JavaScript sources', () => {
        expect(result['src/legacy.js']).toEqual(
            expect.arrayContaining([
                'no-else-return',
                'no-useless-return',
                'no-lonely-if',
                'no-unneeded-ternary',
            ]),
        );
    });

    it('reports unnecessary comparisons and conversions in TypeScript sources', () => {
        expect(result['src/typed.ts']).toEqual(
            expect.arrayContaining([
                '@typescript-eslint/no-unnecessary-boolean-literal-compare',
                '@typescript-eslint/no-unnecessary-template-expression',
                '@typescript-eslint/no-unnecessary-type-conversion',
            ]),
        );
    });

    it('lints untyped spec files without the type-aware rules', () => {
        expect(result['src/typed.spec.ts']).toEqual([]);
    });
});

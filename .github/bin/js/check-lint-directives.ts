/**
 * Fail when a lint directive switches off the checks that guard the other lint directives.
 *
 * `@eslint-community/eslint-comments/no-restricted-disable` blocks inline disables of some rules, and
 * `@eslint-community/eslint-comments/no-use` blocks `/* eslint <rule>: off *\/` config comments. ESLint
 * can't enforce either against an `eslint-disable` comment that also names the guarding rule: the
 * comment disables the report about itself. This check reads the files instead of running ESLint, so
 * no comment can turn it off.
 *
 * It reports:
 * - `eslint-disable`, `eslint-disable-line` and `eslint-disable-next-line` comments naming an
 *   `eslint-comments/` rule
 * - `/* eslint ... *\/` rule config comments
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';

export type Finding = {
    line: number;
    message: string;
};

const SOURCE_FILE = /\.(?:[cm]?[jt]sx?|vue)$/;

const DISABLE_DIRECTIVE = /^\s*eslint-disable(?:-line|-next-line)?(?:\s|$)/;
const CONFIG_DIRECTIVE = /^\s*eslint\s/;

// Lookaheads match at every `/*` and `//`, so a `/*` inside a string can't swallow a real comment after it
const BLOCK_COMMENT = /(?=\/\*([\s\S]*?)\*\/)/g;
const LINE_COMMENT = /(?=\/\/([^\n]*))/g;

const lineAt = (source: string, index: number): number => source.slice(0, index).split('\n').length;

// The rule list ends where the description after ` -- ` starts
const namedRules = (directive: string): string => directive.split(/\s--\s/)[0];

const checkDirective = (text: string, isBlock: boolean): string | null => {
    if (DISABLE_DIRECTIVE.test(text) && namedRules(text).includes('eslint-comments/')) {
        return 'Do not disable the eslint-comments rules, they guard the other lint directives.';
    }

    if (isBlock && CONFIG_DIRECTIVE.test(text)) {
        return 'Do not configure rules inline. Fix the violation, or ask for a change of eslint.config.ts.';
    }

    return null;
};

export const findForbiddenDirectives = (source: string): Finding[] => {
    const findings: Finding[] = [];

    for (const [pattern, isBlock] of [
        [BLOCK_COMMENT, true],
        [LINE_COMMENT, false],
    ] as const) {
        for (const match of source.matchAll(pattern)) {
            const message = checkDirective(match[1], isBlock);

            if (message !== null) {
                findings.push({ line: lineAt(source, match.index), message });
            }
        }
    }

    return findings.sort((a, b) => a.line - b.line);
};

const trackedSourceFiles = (directory: string): string[] =>
    execFileSync(
        'git',
        [
            'ls-files',
            '-z',
            '--',
            directory,
        ],
        { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
    )
        .split('\0')
        .filter((file) => SOURCE_FILE.test(file));

const main = (): void => {
    const directories = process.argv.slice(2);

    if (directories.length === 0) {
        process.stderr.write('usage: check-lint-directives.ts <directory>...\n');
        process.exit(2);
    }

    let failed = false;

    for (const directory of directories) {
        if (!existsSync(directory) || !statSync(directory).isDirectory()) {
            process.stderr.write(`${directory} is no directory.\n`);
            process.exit(2);
        }

        const files = trackedSourceFiles(directory);

        // An empty list means the path or the git checkout is wrong, not that every file is fine
        if (files.length === 0) {
            process.stderr.write(`No tracked source files in ${directory}.\n`);
            process.exit(2);
        }

        for (const file of files) {
            for (const { line, message } of findForbiddenDirectives(readFileSync(file, 'utf8'))) {
                process.stdout.write(`${file}:${line}: ${message}\n`);
                failed = true;
            }
        }

        process.stdout.write(`Scanned ${files.length} files in ${directory}.\n`);
    }

    if (failed) {
        process.exit(1);
    }
};

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}

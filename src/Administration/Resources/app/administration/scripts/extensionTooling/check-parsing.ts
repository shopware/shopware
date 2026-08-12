/**
 * @sw-package framework
 *
 * Pure analysis for the check runner: which source files a tool would act on,
 * and how a completed run's raw output structures into findings. No child
 * processes and no baseline logic — those live in the pipeline and tool-runner
 * modules.
 */

import fs from 'fs';
import path from 'path';
import { canonicalizePath } from './shared';
import type { EslintFinding, TypeScriptFinding } from './baseline';

const LINTABLE_EXTENSIONS = [
    '.ts',
    '.tsx',
    '.vue',
    '.js',
    '.mjs',
    '.cjs',
];
const TYPE_CHECKABLE_EXTENSIONS = [
    '.ts',
    '.tsx',
    '.vue',
];

/**
 * Breadth-first walk of every source path, skipping node_modules, yielding the
 * absolute path of each file whose name matches. Directory entries are visited
 * in name order so the traversal is deterministic — which is what lets callers
 * that stop at the first hit return a stable answer across machines.
 */
function* walkSourceFiles(
    projectRoot: string,
    sourcePaths: string[],
    isMatch: (fileName: string) => boolean,
): Generator<string> {
    for (const sourcePath of sourcePaths) {
        const queue = [path.resolve(projectRoot, sourcePath)];

        while (queue.length > 0) {
            const currentDir = queue.shift() as string;

            if (!fs.existsSync(currentDir)) {
                continue;
            }

            for (const entry of fs
                .readdirSync(currentDir, { withFileTypes: true })
                .sort((left, right) => left.name.localeCompare(right.name))) {
                const entryPath = path.join(currentDir, entry.name);

                if (entry.isDirectory() && entry.name !== 'node_modules') {
                    queue.push(entryPath);
                } else if (entry.isFile() && isMatch(entry.name)) {
                    yield entryPath;
                }
            }
        }
    }
}

/** Canonical, sorted paths of every file matching `isMatch` — the form config coverage is compared against. */
function collectSourceFiles(projectRoot: string, sourcePaths: string[], isMatch: (fileName: string) => boolean): string[] {
    return [...walkSourceFiles(projectRoot, sourcePaths, isMatch)].map(canonicalizePath).sort();
}

/** The first lintable file (any tool-relevant extension) under the source paths, or null — the ESLint probe's sample. */
export function findFirstSourceFile(projectRoot: string, sourcePaths: string[]): string | null {
    for (const file of walkSourceFiles(projectRoot, sourcePaths, (fileName) =>
        LINTABLE_EXTENSIONS.includes(path.extname(fileName)),
    )) {
        return file;
    }

    return null;
}

/**
 * Counts the files vue-tsc would actually type-check (`checkJs` is off in the
 * preset, so plain `.js` sources are parsed but never checked). Spec files are
 * excluded to mirror the generated tsconfigs. Ambient declaration files
 * (`*.d.ts`) are excluded too: they carry no checkable source, and TypeScript
 * resolves them into the program on its own terms rather than as listed input
 * files — counting them would make a config that legitimately includes them via
 * a broad source glob look like it left a discovered file uncovered. Zero means
 * a TypeScript "pass" would be vacuous — reported as `no-files` instead of a
 * bare green.
 */
export function listTypeCheckableFiles(projectRoot: string, sourcePaths: string[]): string[] {
    return collectSourceFiles(
        projectRoot,
        sourcePaths,
        (fileName) =>
            TYPE_CHECKABLE_EXTENSIONS.includes(path.extname(fileName)) &&
            !/\.spec\.(ts|tsx|js)$/.test(fileName) &&
            !fileName.endsWith('.d.ts'),
    );
}

export function countTypeCheckableFiles(projectRoot: string, sourcePaths: string[]): number {
    return listTypeCheckableFiles(projectRoot, sourcePaths).length;
}

/**
 * The spec files the dedicated spec program would type-check
 * (`.spec.ts`/`.spec.tsx`; `.spec.js` is parsed but not type-checked, like any
 * `.js`). Zero means the spec program would be vacuous — reported as no-files.
 */
export function listSpecFiles(projectRoot: string, sourcePaths: string[]): string[] {
    return collectSourceFiles(projectRoot, sourcePaths, (fileName) => /\.spec\.(ts|tsx)$/.test(fileName));
}

export function countSpecFiles(projectRoot: string, sourcePaths: string[]): number {
    return listSpecFiles(projectRoot, sourcePaths).length;
}

export function countTypeScriptFindings(output: string): number {
    return output.split(/\r?\n/).filter((line) => /error TS\d+:/.test(line)).length;
}

export function countEslintFindings(output: string): number {
    const summaryMatch = output.match(/✖ (\d+) problems? \((\d+) errors?, (\d+) warnings?\)/);

    if (summaryMatch) {
        return Number(summaryMatch[1]);
    }

    return 0;
}

/**
 * Structures the TypeScript findings the counter counts — one per
 * `error TSxxxx:` line, in both the compact `file(l,c):` and the pretty
 * `file:l:c -` location formats. Line and column are dropped on purpose: the
 * baseline keys on file + code + message so a recorded finding survives line
 * drift. Indented related-information lines carry no `error TSxxxx:` and are
 * skipped, so the result length matches countTypeScriptFindings.
 */
export function parseTypeScriptFindings(output: string): TypeScriptFinding[] {
    const findings: TypeScriptFinding[] = [];

    for (const line of output.split(/\r?\n/)) {
        const compact = line.match(/^(.+?)\(\d+,\d+\): error (TS\d+): (.*)$/);
        const match = compact ?? line.match(/^(.+?):\d+:\d+ - error (TS\d+): (.*)$/);

        if (match) {
            findings.push({ file: match[1], code: match[2], message: match[3].trim() });
        }
    }

    return findings;
}

/** A file-header line in ESLint's stylish output is a path ending in a lintable-file suffix. */
const ESLINT_FILE_HEADER_PATTERN = /\.(ts|tsx|js|jsx|mjs|cjs|vue|twig|html|json)$/;

/**
 * Structures ESLint's stylish output — a bare file-header line followed by
 * indented `line:col severity message rule` rows (rule id last, separated from
 * the message by column padding). Line and column are dropped (the baseline
 * keys on file + rule + message). Both severities are returned so the length
 * matches countEslintFindings; callers baseline only error-severity findings,
 * since warnings never fail the check.
 */
export function parseEslintFindings(output: string): EslintFinding[] {
    const findings: EslintFinding[] = [];
    let currentFile: string | null = null;
    let lastFinding: EslintFinding | null = null;

    for (const line of output.split(/\r?\n/)) {
        if (line.trim() === '') {
            lastFinding = null;

            continue;
        }

        const row = line.match(/^\s+\d+:\d+\s+(error|warning)\s+(.+)$/);

        if (row && currentFile) {
            const ruleMatch = row[2].match(/^(.*?)\s{2,}(\S+)$/);
            const finding: EslintFinding = {
                file: currentFile,
                rule: ruleMatch ? ruleMatch[2] : '',
                message: (ruleMatch ? ruleMatch[1] : row[2]).trim(),
                severity: row[1] as 'error' | 'warning',
            };

            findings.push(finding);
            lastFinding = finding;

            continue;
        }

        // A file header is a path to a lintable file. ESLint prints multi-line
        // rule messages (e.g. @typescript-eslint/unbound-method) with their
        // continuation lines un-indented; requiring a source-file suffix keeps
        // those from being mistaken for a new file header, which would otherwise
        // corrupt the file attribution of every finding below them.
        if (!/^\s/.test(line) && ESLINT_FILE_HEADER_PATTERN.test(line.trim())) {
            currentFile = line.trim();
            lastFinding = null;

            continue;
        }

        // The rule id of a multi-line message is printed on its last
        // (un-indented) continuation line — attribute it to the finding above.
        const continuationRule = line.match(/\s{2,}(\S+)$/);

        if (lastFinding && continuationRule && continuationRule[1].includes('/')) {
            lastFinding.rule = continuationRule[1];
        }
    }

    return findings;
}

/**
 * Splits a tool's output into diagnostic blocks: one `error TSxxxx:` line plus
 * the indented related-information lines belonging to it. Anything before the
 * first diagnostic stays in its own leading block so no text is lost.
 */
function splitDiagnosticBlocks(output: string): string[] {
    const blocks: string[] = [];

    for (const line of output.split(/\r?\n/)) {
        if (blocks.length === 0 || /error TS\d+:/.test(line)) {
            blocks.push(line);

            continue;
        }

        blocks[blocks.length - 1] += `\n${line}`;
    }

    return blocks;
}

/**
 * Joins the outputs of several programs, printing each diagnostic once. Every
 * program compiles the shared type surface, so a diagnostic there was otherwise
 * repeated once per program in the visible output even though the structured
 * findings counted it once. Blocks are compared whole, so a dropped diagnostic
 * never leaves its related-information lines orphaned behind it.
 */
export function joinProgramOutputs(outputs: string[]): string {
    const seen = new Set<string>();

    return outputs
        .map((output) =>
            splitDiagnosticBlocks(output)
                .filter((block) => {
                    const key = block.trim();

                    if (key === '' || seen.has(key)) {
                        return key === '';
                    }

                    seen.add(key);

                    return true;
                })
                .join('\n')
                .trim(),
        )
        .filter((output) => output !== '')
        .join('\n\n');
}

/**
 * Merges findings from several programs that overlap on the shared type
 * surface: a finding is kept as many times as the single program that reported
 * it most, so duplicate surface diagnostics collapse while a genuinely repeated
 * in-file finding survives.
 */
export function deduplicateByMaximumMultiplicity<F>(groups: F[][], keyOf: (finding: F) => string): F[] {
    const representatives = new Map<string, F>();
    const maximumCounts = new Map<string, number>();

    for (const findings of groups) {
        const groupCounts = new Map<string, number>();

        for (const finding of findings) {
            const key = keyOf(finding);

            representatives.set(key, representatives.get(key) ?? finding);
            groupCounts.set(key, (groupCounts.get(key) ?? 0) + 1);
        }

        for (const [
            key,
            count,
        ] of groupCounts) {
            maximumCounts.set(key, Math.max(maximumCounts.get(key) ?? 0, count));
        }
    }

    return [...maximumCounts.entries()].flatMap(
        ([
            key,
            count,
        ]) => Array.from({ length: count }, () => representatives.get(key) as F),
    );
}

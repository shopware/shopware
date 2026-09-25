/**
 * @sw-package framework
 *
 * Resolves the files a diff-scoped mutation run should mutate. The git side collects changed
 * files relative to the Administration root, the pure resolver maps them to mutation targets:
 * specs are traced back to the code they test, and files Stryker cannot instrument are dropped
 * with a reason so the caller can print why. Component entries stay in: their Twig template is
 * not mutated, but the script logic around it is.
 */

import { execFileSync } from 'child_process';
import path from 'path';

export interface ResolverFs {
    exists(relativePath: string): boolean;
    readFile(relativePath: string): string;
}

export interface SkippedFile {
    file: string;
    reason: string;
}

export interface ResolvedTargets {
    targets: string[];
    skipped: SkippedFile[];
}

const SOURCE_EXTENSIONS = [
    '.js',
    '.ts',
];
const SPEC_FILE = /^(.+)\.spec\.(js|ts)$/;
const SPEC_DIRECTORY = /^(.+)\.spec\/.+\.spec\.(js|ts)$/;

function toPosix(file: string): string {
    return file.split(path.sep).join('/');
}

/**
 * Maps a spec path to the source file it tests, or null when none exists.
 * `foo.spec.ts` and `foo.spec/bar.spec.ts` both resolve to `foo.js` or `foo.ts`.
 */
export function sourceForSpec(specPath: string, fs: ResolverFs): string | null {
    const match = SPEC_DIRECTORY.exec(specPath) ?? SPEC_FILE.exec(specPath);

    if (!match) {
        return null;
    }

    const candidates = SOURCE_EXTENSIONS.map((extension) => `${match[1]}${extension}`);

    return candidates.find((candidate) => fs.exists(candidate)) ?? null;
}

function isSpec(file: string): boolean {
    return SPEC_FILE.test(file) || SPEC_DIRECTORY.test(file);
}

/**
 * Pure part of the pipeline; `changedFiles` are POSIX paths relative to the Administration root.
 */
export function resolveMutationTargets(changedFiles: string[], fs: ResolverFs): ResolvedTargets {
    const targets = new Set<string>();
    const skipped: SkippedFile[] = [];

    for (const changed of changedFiles.map(toPosix)) {
        if (!changed.startsWith('src/') || !SOURCE_EXTENSIONS.includes(path.posix.extname(changed))) {
            continue;
        }

        if (changed.endsWith('.d.ts')) {
            skipped.push({ file: changed, reason: 'type declarations carry no runtime code' });
            continue;
        }

        let candidate = changed;

        if (isSpec(changed)) {
            const source = sourceForSpec(changed, fs);

            if (!source) {
                skipped.push({ file: changed, reason: 'spec without a matching source file next to it' });
                continue;
            }

            candidate = source;
        } else if (!fs.exists(changed)) {
            skipped.push({ file: changed, reason: 'file no longer exists' });
            continue;
        }

        if (targets.has(candidate)) {
            continue;
        }

        if (fs.readFile(candidate).includes('import.meta.glob')) {
            skipped.push({
                file: candidate,
                reason: 'import.meta.glob loader; the glob must stay a literal for the Jest transform',
            });
            continue;
        }

        targets.add(candidate);
    }

    return { targets: [...targets].sort(), skipped };
}

function git(cwd: string, args: string[]): string[] {
    return execFileSync('git', args, { cwd, encoding: 'utf8' })
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

/**
 * Committed changes since the merge base with `baseRef`, plus tracked and untracked working tree
 * changes. Paths are relative to `cwd`, deleted files are left out.
 */
export function collectChangedFiles(cwd: string, baseRef: string): string[] {
    const [mergeBase] = git(cwd, [
        'merge-base',
        'HEAD',
        baseRef,
    ]);
    const changed = git(cwd, [
        'diff',
        '--name-only',
        '--diff-filter=d',
        '--relative',
        mergeBase,
    ]);
    const untracked = git(cwd, [
        'ls-files',
        '--others',
        '--exclude-standard',
    ]);

    return [
        ...new Set([
            ...changed,
            ...untracked,
        ]),
    ];
}

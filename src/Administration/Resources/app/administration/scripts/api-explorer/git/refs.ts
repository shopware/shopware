/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { GitRefError } from './errors';
import { parsePullRequestRef, resolvePullRequestRef, type PullRequestResolver } from './pull-request';
import { GitCommandError, runGit } from './run-git';

export { GitRefError };

export type GitRefKind = 'branch' | 'remote' | 'tag';

export interface GitRef {
    /** Short name as accepted by `resolveRef`, e.g. 'trunk', 'origin/trunk' or 'v6.6.10.29'. */
    name: string;
    kind: GitRefKind;
    commit: string;
}

export interface WorktreeStatus {
    /** Null for a detached HEAD or an unborn branch. */
    branch: string | null;
    /** Null for a repository without commits. */
    head: string | null;
    /** Modified, staged, deleted, renamed and untracked files below the admin root; ignored files do not count. */
    changedFiles: number;
}

const REF_PREFIXES: Array<[string, GitRefKind]> = [
    [
        'refs/heads/',
        'branch',
    ],
    [
        'refs/remotes/',
        'remote',
    ],
    [
        'refs/tags/',
        'tag',
    ],
];

const KIND_ORDER: GitRefKind[] = [
    'branch',
    'remote',
    'tag',
];

const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

function naturalCompare(a: string, b: string): number {
    return collator.compare(a, b);
}

interface ParsedVersion {
    numbers: number[];
    suffix: string;
}

function parseVersion(name: string): ParsedVersion | null {
    const match = /^v?(\d+(?:\.\d+)*)(?:[-+.]?(.*))?$/i.exec(name);

    if (!match) {
        return null;
    }

    return { numbers: match[1].split('.').map(Number), suffix: match[2] ?? '' };
}

/** Descending: v6.7.0.0 before v6.7.0.0-rc1 before v6.6.10.29; names that are no version go last. */
export function compareVersionsDescending(a: string, b: string): number {
    const versionA = parseVersion(a);
    const versionB = parseVersion(b);

    if (!versionA || !versionB) {
        if (versionA !== versionB) {
            return versionA ? -1 : 1;
        }

        return naturalCompare(a, b);
    }

    const length = Math.max(versionA.numbers.length, versionB.numbers.length);

    for (let index = 0; index < length; index += 1) {
        const difference = (versionB.numbers[index] ?? 0) - (versionA.numbers[index] ?? 0);

        if (difference !== 0) {
            return difference;
        }
    }

    if (versionA.suffix === '' || versionB.suffix === '') {
        return (versionA.suffix === '' ? 0 : 1) - (versionB.suffix === '' ? 0 : 1);
    }

    return naturalCompare(versionB.suffix, versionA.suffix) || naturalCompare(a, b);
}

function compareRefs(a: GitRef, b: GitRef): number {
    const kindDifference = KIND_ORDER.indexOf(a.kind) - KIND_ORDER.indexOf(b.kind);

    if (kindDifference !== 0) {
        return kindDifference;
    }

    return a.kind === 'tag' ? compareVersionsDescending(a.name, b.name) : naturalCompare(a.name, b.name);
}

/** Local branches and remote branches by name, then tags by version descending. */
export async function listRefs(repoRoot: string): Promise<GitRef[]> {
    const output = await runGit(repoRoot, [
        'for-each-ref',
        '--format=%(refname)%00%(objectname)%00%(*objectname)%00%(symref)',
        'refs/heads',
        'refs/remotes',
        'refs/tags',
    ]);
    const refs: GitRef[] = [];

    for (const line of output.split('\n')) {
        const [
            refName,
            objectName,
            peeledName,
            symref,
        ] = line.split('\0');
        const prefix = REF_PREFIXES.find(([candidate]) => refName?.startsWith(candidate));

        // Skips symbolic refs such as refs/remotes/origin/HEAD.
        if (!prefix || !objectName || symref) {
            continue;
        }

        refs.push({ name: refName.slice(prefix[0].length), kind: prefix[1], commit: peeledName || objectName });
    }

    return refs.sort(compareRefs);
}

export function assertSafeRef(ref: string): void {
    if (ref.trim() === '' || ref.startsWith('-') || /[\0\n\r]/.test(ref)) {
        throw new GitRefError(`Invalid git ref: ${JSON.stringify(ref)}`);
    }
}

export interface ResolveRefOptions {
    /** Defaults to the shared resolver that caches pull requests for a minute. */
    pullRequests?: PullRequestResolver;
    /** Fetches a pull request again instead of using the cached resolution. */
    refresh?: boolean;
}

/**
 * Resolves a branch, remote branch, tag, (abbreviated) sha, `pr:<n>` or `pr-base:<n>` to the full commit sha.
 * Pull request refs fetch the pull request below `refs/api-explorer/`.
 */
export async function resolveRef(repoRoot: string, ref: string, options: ResolveRefOptions = {}): Promise<string> {
    assertSafeRef(ref);

    const pullRequestRef = parsePullRequestRef(ref);

    if (pullRequestRef) {
        return resolvePullRequestRef(repoRoot, pullRequestRef, options);
    }

    try {
        const output = await runGit(repoRoot, [
            'rev-parse',
            '--verify',
            '--quiet',
            '--end-of-options',
            `${ref}^{commit}`,
        ]);

        return output.trim();
    } catch (error) {
        if (error instanceof GitCommandError) {
            const detail = error.stderr.trim();
            throw new GitRefError(`Unknown git ref: ${ref}${detail ? ` (${detail})` : ''}`);
        }

        throw error;
    }
}

export async function findRepoRoot(directory: string): Promise<string> {
    const output = await runGit(directory, [
        'rev-parse',
        '--show-toplevel',
    ]);

    return output.trim();
}

/** Posix path of `adminRoot` relative to `repoRoot`, '' when both are the same directory. */
export function toAdminRelativePath(repoRoot: string, adminRoot: string): string {
    const relative = path.relative(fs.realpathSync(repoRoot), fs.realpathSync(adminRoot));

    if (relative.startsWith('..') || path.isAbsolute(relative)) {
        throw new Error(`${adminRoot} is not inside the repository ${repoRoot}`);
    }

    return relative.split(path.sep).join('/');
}

async function tryRunGit(repoRoot: string, args: string[]): Promise<string | null> {
    try {
        return (await runGit(repoRoot, args)).trim();
    } catch (error) {
        if (error instanceof GitCommandError) {
            return null;
        }

        throw error;
    }
}

function countStatusEntries(output: string): number {
    const entries = output.split('\0').filter((entry) => entry !== '');
    let count = 0;

    for (let index = 0; index < entries.length; index += 1) {
        count += 1;

        // Renames and copies are followed by an extra entry holding the source path.
        if (/^[RC]|^.[RC]/.test(entries[index])) {
            index += 1;
        }
    }

    return count;
}

export async function getWorktreeStatus(repoRoot: string, adminRelativePath: string): Promise<WorktreeStatus> {
    const head = await tryRunGit(repoRoot, [
        'rev-parse',
        '--verify',
        '--quiet',
        'HEAD',
    ]);
    const branch = head
        ? await tryRunGit(repoRoot, [
              'rev-parse',
              '--abbrev-ref',
              'HEAD',
          ])
        : null;
    const status = await runGit(repoRoot, [
        'status',
        '--porcelain=v1',
        '-z',
        '--untracked-files=all',
        '--',
        adminRelativePath === '' ? '.' : adminRelativePath,
    ]);

    return {
        branch: branch && branch !== 'HEAD' ? branch : null,
        head: head || null,
        changedFiles: countStatusEntries(status),
    };
}

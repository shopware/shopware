/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { GitRefError } from './errors';
import { GitCommandError, runGit } from './run-git';

export interface GitHubRepository {
    owner: string;
    repo: string;
}

export interface PullRequestTarget extends GitHubRepository {
    number: number;
}

/** Without owner and repo the pull request belongs to the GitHub repository behind `origin`. */
export type PullRequestInput = PullRequestTarget | { number: number; owner?: undefined; repo?: undefined };

export interface PullRequestRef {
    side: 'head' | 'base';
    input: PullRequestInput;
}

export interface PullRequestResolution extends PullRequestTarget {
    /** Null when the GitHub API was unavailable. */
    title: string | null;
    state: 'open' | 'closed' | null;
    merged: boolean | null;
    url: string;
    baseRef: string;
    headSha: string;
    /** Tip of `baseRef` at the time of the fetch. */
    baseSha: string;
    mergeBaseSha: string;
    warnings: string[];
}

export interface FetchResponse {
    ok: boolean;
    status: number;
    json(): Promise<unknown>;
}

export type FetchLike = (
    url: string,
    init: { headers: Record<string, string>; signal: AbortSignal },
) => Promise<FetchResponse>;

export interface PullRequestResolverOptions {
    fetch?: FetchLike;
    /** URL `git fetch` reads from; defaults to the HTTPS URL on github.com. */
    repositoryUrl?: (repository: GitHubRepository) => string;
    env?: NodeJS.ProcessEnv;
    cacheTtlMs?: number;
    now?: () => number;
}

export interface PullRequestResolver {
    resolve(repoRoot: string, input: PullRequestInput, options?: { refresh?: boolean }): Promise<PullRequestResolution>;
}

export const PULL_REQUEST_NAMESPACE = 'refs/api-explorer/pull';

const FALLBACK_BASE_REF = 'trunk';
const DEFAULT_CACHE_TTL_MS = 60_000;
const API_TIMEOUT_MS = 10_000;
const FETCH_TIMEOUT_MS = 300_000;
const MAX_PULL_REQUEST_NUMBER = 2 ** 31 - 1;
const SHA = /^[0-9a-f]{40}$|^[0-9a-f]{64}$/;

const INPUT_FORMS = 'expected <n>, #<n>, <owner>/<repo>/<n> or https://github.com/<owner>/<repo>/pull/<n>';

function isSafeName(name: string): boolean {
    return /^[A-Za-z0-9_.-]+$/.test(name) && !/^[.-]/.test(name) && !name.includes('..') && !name.endsWith('.lock');
}

/** The branch only sits on the source side of a refspec, so every valid branch name without `:` is fine. */
async function isFetchableBranch(repoRoot: string, branch: string): Promise<boolean> {
    if (branch === '' || /^[+-]|[:\0\n\r]/.test(branch)) {
        return false;
    }

    try {
        await runGit(repoRoot, [
            'check-ref-format',
            `refs/heads/${branch}`,
        ]);

        return true;
    } catch (error) {
        if (error instanceof GitCommandError) {
            return false;
        }

        throw error;
    }
}

function toNumber(digits: string, value: string): number {
    const number = Number(digits);

    if (!Number.isSafeInteger(number) || number < 1 || number > MAX_PULL_REQUEST_NUMBER) {
        throw new GitRefError(`Invalid pull request number in ${JSON.stringify(value)}`);
    }

    return number;
}

/** Accepts `19666`, `#19666`, `owner/repo/19666`, `owner/repo#19666` and GitHub pull request URLs. */
export function parsePullRequestInput(value: string): PullRequestInput {
    const input = value.trim().replace(/^pr(?:-base)?:/, '');
    const numberOnly = /^#?(\d+)$/.exec(input);

    if (numberOnly) {
        return { number: toNumber(numberOnly[1], value) };
    }

    const match =
        /^https?:\/\/(?:www\.)?github\.com\/([^/]+)\/([^/]+)\/pull\/(\d+)(?:[/?#].*)?$/i.exec(input) ??
        /^([^/#]+)\/([^/#]+)(?:\/pull\/|\/|#)(\d+)$/.exec(input);

    if (!match) {
        throw new GitRefError(`Invalid pull request ${JSON.stringify(value)}: ${INPUT_FORMS}`);
    }

    const [
        ,
        owner,
        repo,
        digits,
    ] = match;

    if (!isSafeName(owner) || !isSafeName(repo)) {
        throw new GitRefError(`Invalid GitHub repository in ${JSON.stringify(value)}`);
    }

    return { owner: owner.toLowerCase(), repo: repo.toLowerCase(), number: toNumber(digits, value) };
}

/** `pr:<input>` is the pull request head, `pr-base:<input>` the merge base with its base branch; null for other refs. */
export function parsePullRequestRef(ref: string): PullRequestRef | null {
    const match = /^(pr|pr-base):(.*)$/s.exec(ref);

    if (!match) {
        return null;
    }

    return { side: match[1] === 'pr' ? 'head' : 'base', input: parsePullRequestInput(match[2]) };
}

export function parseGitHubRemoteUrl(url: string): GitHubRepository | null {
    const match =
        /^(?:git@github\.com:|ssh:\/\/git@github\.com\/|(?:https?|git):\/\/(?:[^@/]+@)?github\.com\/)([^/]+)\/([^/]+?)(?:\.git)?\/?$/i.exec(
            url.trim(),
        );

    if (!match || !isSafeName(match[1]) || !isSafeName(match[2])) {
        return null;
    }

    return { owner: match[1].toLowerCase(), repo: match[2].toLowerCase() };
}

async function originRepository(repoRoot: string): Promise<GitHubRepository> {
    let url = '';

    try {
        url = await runGit(repoRoot, [
            'config',
            '--get',
            'remote.origin.url',
        ]);
    } catch (error) {
        if (!(error instanceof GitCommandError)) {
            throw error;
        }
    }

    const repository = parseGitHubRemoteUrl(url);

    if (!repository) {
        throw new GitRefError('The remote "origin" is no GitHub repository; name the pull request as <owner>/<repo>/<n>.');
    }

    return repository;
}

interface PullRequestMetadata {
    title: string;
    state: 'open' | 'closed';
    merged: boolean;
    url: string;
    /** Not validated yet, see `isFetchableBranch`. */
    baseRef: string;
    headSha: string;
    /** For merged pull requests the merge, squash or last rebased commit on the base branch. */
    mergeCommitSha: string | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function parseMetadata(body: unknown): PullRequestMetadata {
    const base = isRecord(body) && isRecord(body.base) ? body.base : {};
    const head = isRecord(body) && isRecord(body.head) ? body.head : {};
    const { title, state, merged, html_url: url, merge_commit_sha: mergeCommitSha } = isRecord(body) ? body : {};

    if (
        typeof title !== 'string' ||
        (state !== 'open' && state !== 'closed') ||
        typeof merged !== 'boolean' ||
        typeof url !== 'string' ||
        !url.startsWith('https://github.com/') ||
        typeof base.ref !== 'string' ||
        typeof head.sha !== 'string' ||
        !SHA.test(head.sha)
    ) {
        throw new Error('unexpected response');
    }

    return {
        title,
        state,
        merged,
        url,
        baseRef: base.ref,
        headSha: head.sha,
        mergeCommitSha: typeof mergeCommitSha === 'string' && SHA.test(mergeCommitSha) ? mergeCommitSha : null,
    };
}

class GitHubHttpError extends Error {
    constructor(readonly status: number) {
        super(`HTTP ${status}`);
    }
}

async function requestGitHub(
    repository: GitHubRepository,
    apiPath: string,
    fetchImpl: FetchLike,
    env: NodeJS.ProcessEnv,
): Promise<unknown> {
    const token = env.GITHUB_TOKEN || env.GH_TOKEN;
    const response = await fetchImpl(`https://api.github.com/repos/${repository.owner}/${repository.repo}/${apiPath}`, {
        headers: {
            Accept: 'application/vnd.github+json',
            'User-Agent': 'shopware-admin-api-explorer',
            'X-GitHub-Api-Version': '2022-11-28',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        signal: AbortSignal.timeout(API_TIMEOUT_MS),
    });

    if (!response.ok) {
        throw new GitHubHttpError(response.status);
    }

    return response.json();
}

interface ShallowBoundary {
    /** Committer date of the newest shallow commit, ISO 8601. */
    date: string;
    epochMs: number;
}

async function readShallowBoundary(repoRoot: string): Promise<ShallowBoundary | null> {
    const isShallow = await runGit(repoRoot, [
        'rev-parse',
        '--is-shallow-repository',
    ]);

    if (isShallow.trim() !== 'true') {
        return null;
    }

    const shallowFile = path.resolve(
        repoRoot,
        (
            await runGit(repoRoot, [
                'rev-parse',
                '--git-path',
                'shallow',
            ])
        ).trim(),
    );
    const commits = fs
        .readFileSync(shallowFile, 'utf8')
        .split('\n')
        .filter((line) => SHA.test(line));
    const dates = await runGit(
        repoRoot,
        [
            'log',
            '--no-walk',
            '--stdin',
            '--format=%ct %cI',
        ],
        `${commits.join('\n')}\n`,
    );
    let boundary: ShallowBoundary | null = null;

    for (const line of dates.split('\n')) {
        const [
            seconds,
            date,
        ] = line.split(' ');
        const epochMs = Number(seconds) * 1000;

        if (date && (!boundary || epochMs > boundary.epochMs)) {
            boundary = { date, epochMs };
        }
    }

    return boundary ?? { date: 'an unknown date', epochMs: Infinity };
}

function shallowError(label: string, boundary: ShallowBoundary, forkDate: string | null): GitRefError {
    const fork = forkDate ? ` (its merge base is from ${forkDate})` : '';

    return new GitRefError(
        `The repository is a shallow clone whose history starts at ${boundary.date}; ${label} forks before that${fork}. Run \`git fetch --unshallow\` to compare it.`,
    );
}

/** Refuses before fetching when the fork point lies beyond the shallow boundary, since the fetch would download all of its history. */
async function assertForkInsideShallowHistory(
    repoRoot: string,
    target: PullRequestTarget,
    label: string,
    metadata: PullRequestMetadata | null,
    boundary: ShallowBoundary,
    fetchImpl: FetchLike,
    env: NodeJS.ProcessEnv,
): Promise<void> {
    const unknownSize = (reason: string) =>
        new GitRefError(
            `The repository is a shallow clone whose history starts at ${boundary.date}, and ${reason}, so fetching ${label} could download its whole history. Run \`git fetch --unshallow\` to compare it.`,
        );

    if (!metadata) {
        throw unknownSize('the GitHub API is unavailable');
    }

    let mergeBaseCommit: Record<string, unknown> = {};

    try {
        let compareBase = metadata.baseRef.split('/').map(encodeURIComponent).join('/');

        if (metadata.merged && metadata.mergeCommitSha) {
            const commit = await requestGitHub(target, `commits/${metadata.mergeCommitSha}`, fetchImpl, env);
            const parent = isRecord(commit) && Array.isArray(commit.parents) ? (commit.parents[0] as unknown) : null;

            if (!isRecord(parent) || typeof parent.sha !== 'string' || !SHA.test(parent.sha)) {
                throw new Error('unexpected response');
            }

            compareBase = parent.sha;
        }

        const comparison = await requestGitHub(target, `compare/${compareBase}...${metadata.headSha}`, fetchImpl, env);
        mergeBaseCommit = isRecord(comparison) && isRecord(comparison.merge_base_commit) ? comparison.merge_base_commit : {};
    } catch (error) {
        throw unknownSize(
            `its merge base could not be looked up (${error instanceof Error ? error.message : String(error)})`,
        );
    }

    const sha = mergeBaseCommit.sha;
    const details = isRecord(mergeBaseCommit.commit) ? mergeBaseCommit.commit : {};
    const committer = isRecord(details.committer) ? details.committer : {};
    const date = typeof committer.date === 'string' ? committer.date : null;

    if (typeof sha !== 'string' || !SHA.test(sha) || date === null || Number.isNaN(Date.parse(date))) {
        throw unknownSize('its merge base could not be looked up (unexpected response)');
    }

    if (!(await tryResolveCommit(repoRoot, sha)) && Date.parse(date) < boundary.epochMs) {
        throw shallowError(label, boundary, date);
    }
}

async function tryResolveCommit(repoRoot: string, ref: string): Promise<string | null> {
    try {
        return (
            await runGit(repoRoot, [
                'rev-parse',
                '--verify',
                '--quiet',
                '--end-of-options',
                `${ref}^{commit}`,
            ])
        ).trim();
    } catch (error) {
        if (error instanceof GitCommandError) {
            return null;
        }

        throw error;
    }
}

function pullRequestLabel(target: PullRequestTarget): string {
    return `pull request #${target.number} of ${target.owner}/${target.repo}`;
}

/** Writes nothing but the refs below `namespace`: no FETCH_HEAD, tags, remote-tracking branches or maintenance. */
async function fetchPullRequest(
    repoRoot: string,
    url: string,
    target: PullRequestTarget,
    baseRef: string,
    mergeCommitSha: string | null,
    namespace: string,
): Promise<string[]> {
    const label = pullRequestLabel(target);

    try {
        await runGit(
            repoRoot,
            [
                'fetch',
                '--quiet',
                '--no-tags',
                '--no-write-fetch-head',
                '--no-recurse-submodules',
                '--no-auto-maintenance',
                '--no-write-commit-graph',
                '--no-prune',
                '--refmap=',
                '--end-of-options',
                url,
                `+refs/pull/${target.number}/head:${namespace}/head`,
                `+refs/heads/${baseRef}:${namespace}/base`,
                ...(mergeCommitSha ? [`+${mergeCommitSha}:${namespace}/merge`] : []),
            ],
            undefined,
            { timeoutMs: FETCH_TIMEOUT_MS },
        );

        return [];
    } catch (error) {
        if (!(error instanceof GitCommandError)) {
            throw error;
        }

        const detail = error.stderr.trim();

        if (/couldn't find remote ref refs\/pull\//.test(detail)) {
            throw new GitRefError(`Unknown ${label}`);
        }

        if (/couldn't find remote ref refs\/heads\//.test(detail)) {
            throw new GitRefError(`The base branch ${baseRef} of ${label} does not exist`);
        }

        const fetchedBefore =
            (await tryResolveCommit(repoRoot, `${namespace}/head`)) &&
            (await tryResolveCommit(repoRoot, `${namespace}/base`));

        if (!fetchedBefore) {
            throw new Error(`Fetching ${label} failed: ${detail}`);
        }

        return [`Fetching failed (${detail}); using the refs fetched earlier.`];
    }
}

async function mergeBase(repoRoot: string, head: string, base: string, onMissing: () => GitRefError): Promise<string> {
    try {
        return (
            await runGit(repoRoot, [
                'merge-base',
                '--end-of-options',
                head,
                base,
            ])
        ).trim();
    } catch (error) {
        if (error instanceof GitCommandError && error.exitCode === 1) {
            throw onMissing();
        }

        throw error;
    }
}

async function resolveUncached(
    repoRoot: string,
    target: PullRequestTarget,
    fetchImpl: FetchLike,
    repositoryUrl: (repository: GitHubRepository) => string,
    env: NodeJS.ProcessEnv,
): Promise<PullRequestResolution> {
    const warnings: string[] = [];
    const label = pullRequestLabel(target);
    const boundary = await readShallowBoundary(repoRoot);
    let metadata: PullRequestMetadata | null = null;

    try {
        metadata = parseMetadata(await requestGitHub(target, `pulls/${target.number}`, fetchImpl, env));
    } catch (error) {
        // Without a token a private repository answers 404 as well, which only the fetch can tell apart.
        if (boundary && error instanceof GitHubHttpError && error.status === 404) {
            throw new GitRefError(`Unknown ${label}`);
        }

        const reason = error instanceof Error ? error.message : String(error);
        warnings.push(`GitHub API unavailable (${reason}): assumed base branch ${FALLBACK_BASE_REF}, title unknown.`);
    }

    const baseRef = metadata?.baseRef ?? FALLBACK_BASE_REF;

    if (!(await isFetchableBranch(repoRoot, baseRef))) {
        throw new GitRefError(
            `The base branch ${JSON.stringify(baseRef)} of ${label} is no branch name that can be fetched`,
        );
    }

    if (boundary) {
        await assertForkInsideShallowHistory(repoRoot, target, label, metadata, boundary, fetchImpl, env);
    }

    const mergeCommitSha = metadata?.merged ? metadata.mergeCommitSha : null;
    const namespace = `${PULL_REQUEST_NAMESPACE}/${target.owner}/${target.repo}/${target.number}`;

    warnings.push(...(await fetchPullRequest(repoRoot, repositoryUrl(target), target, baseRef, mergeCommitSha, namespace)));

    const headSha = await tryResolveCommit(repoRoot, `${namespace}/head`);
    const baseSha = await tryResolveCommit(repoRoot, `${namespace}/base`);

    if (!headSha || !baseSha) {
        throw new Error(`The fetched refs below ${namespace} are missing`);
    }

    if (metadata && metadata.headSha !== headSha) {
        warnings.push(`GitHub reports head ${metadata.headSha}, fetched ${headSha}.`);
    }

    // The base branch tip right before the merge works for merge commits, squash and rebase merges alike.
    const baseBeforeMerge = mergeCommitSha ? await tryResolveCommit(repoRoot, `${namespace}/merge^1`) : null;
    const mergeBaseSha = await mergeBase(repoRoot, headSha, baseBeforeMerge ?? baseSha, () =>
        boundary
            ? shallowError(label, boundary, null)
            : new GitRefError(`The head and the base branch ${baseRef} of ${label} have no common ancestor`),
    );

    if (mergeBaseSha === headSha) {
        warnings.push(`The head is already part of ${baseRef}, so the comparison is empty.`);
    }

    return {
        ...target,
        title: metadata?.title ?? null,
        state: metadata?.state ?? null,
        merged: metadata?.merged ?? null,
        url: metadata?.url ?? `https://github.com/${target.owner}/${target.repo}/pull/${target.number}`,
        baseRef,
        headSha,
        baseSha,
        mergeBaseSha,
        warnings,
    };
}

export function createPullRequestResolver({
    fetch: fetchImpl = (url, init) => fetch(url, init),
    repositoryUrl = ({ owner, repo }) => `https://github.com/${owner}/${repo}.git`,
    env = process.env,
    cacheTtlMs = DEFAULT_CACHE_TTL_MS,
    now = Date.now,
}: PullRequestResolverOptions = {}): PullRequestResolver {
    const cache = new Map<string, { promise: Promise<PullRequestResolution>; expiresAt: number }>();

    return {
        async resolve(repoRoot, input, { refresh = false } = {}) {
            const target: PullRequestTarget = input.owner
                ? input
                : { ...(await originRepository(repoRoot)), number: input.number };
            const key = `${path.resolve(repoRoot)}\0${target.owner}/${target.repo}/${target.number}`;
            const cached = cache.get(key);

            // A pending resolution is fresh enough for a refresh, and a second fetch would race for the same refs.
            if (cached && (cached.expiresAt === Infinity || (!refresh && cached.expiresAt > now()))) {
                return cached.promise;
            }

            const entry = {
                promise: resolveUncached(repoRoot, target, fetchImpl, repositoryUrl, env),
                expiresAt: Infinity,
            };
            cache.set(key, entry);
            entry.promise.then(
                () => {
                    entry.expiresAt = now() + cacheTtlMs;
                },
                () => {
                    if (cache.get(key) === entry) {
                        cache.delete(key);
                    }
                },
            );

            return entry.promise;
        },
    };
}

let defaultResolver: PullRequestResolver | null = null;

export function getDefaultPullRequestResolver(): PullRequestResolver {
    defaultResolver ??= createPullRequestResolver();

    return defaultResolver;
}

export async function resolvePullRequestRef(
    repoRoot: string,
    { side, input }: PullRequestRef,
    {
        pullRequests = getDefaultPullRequestResolver(),
        refresh = false,
    }: { pullRequests?: PullRequestResolver; refresh?: boolean },
): Promise<string> {
    const resolution = await pullRequests.resolve(repoRoot, input, { refresh });

    return side === 'head' ? resolution.headSha : resolution.mergeBaseSha;
}

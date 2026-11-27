/**
 * @sw-package framework
 *
 * Reads commits of a repository from GitHub without a login. The REST API names the commits and lists their
 * files, raw.githubusercontent.com serves the contents, and both answer requests from any origin. A commit of a
 * fork is readable through the repository its pull request targets.
 */
import { textOf } from './http';
import type { HttpFetch, HttpResponse } from './http';
import type { FileList, RemoteSource } from './remote-source-tree';

/** @private */
export interface GitHubOptions {
    /** `<owner>/<name>` */
    repository: string;
    /** The path of the Administration folder in the repository. */
    administration: string;
    fetch: HttpFetch;
    /** Lifts the limit of 60 API requests per hour. Only the API gets it. */
    token?: string;
}

/**
 * @private
 *
 * The two commits a comparison is about.
 */
export interface Comparison {
    base: string;
    head: string;
}

/**
 * @private
 *
 * A failure the page shows as it is.
 */
export class GitHubError extends Error {}

/** @private */
export interface GitHub extends RemoteSource {
    /** The head of the pull request against the commit it branched off its base branch. */
    comparisonOfPullRequest(number: number): Promise<Comparison>;
    /** The commit a branch, a tag or a hash names. */
    commitOf(revision: string): Promise<string>;
    /** The page of the line that a finding names as `<file>:<line>`. */
    sourceUrl(commit: string, at: string): string;
}

interface PullRequest {
    head: { sha: string };
    base: { ref: string };
}

interface Tree {
    truncated: boolean;
    tree: Array<{ path: string; type: string; sha: string }>;
}

const COMMIT_HASH = /^[0-9a-f]{40}$/;
const READ_ATTEMPTS = 3;

function encodeRevision(revision: string): string {
    return revision.split('/').map(encodeURIComponent).join('/');
}

function apiFailure(response: HttpResponse, subject: string): GitHubError {
    if (response.headers.get('x-ratelimit-remaining') === '0') {
        const reset = new Date(Number(response.headers.get('x-ratelimit-reset')) * 1000);

        return new GitHubError(
            `GitHub allows 60 requests per hour without a login, and they are used up until ${reset.toLocaleTimeString()}.`,
        );
    }

    return new GitHubError(
        response.status === 404 ? `GitHub does not know ${subject}.` : `GitHub answered ${response.status} for ${subject}.`,
    );
}

/** @private */
export function createGitHub({ repository, administration, fetch: request, token }: GitHubOptions): GitHub {
    const prefix = `${administration}/`;

    const api = async (path: string, subject: string, accept: string): Promise<HttpResponse> => {
        const response = await request(`https://api.github.com/repos/${repository}/${path}`, {
            headers: { Accept: accept, ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        });

        if (!response.ok) {
            throw apiFailure(response, subject);
        }

        return response;
    };

    const json = async <Body>(path: string, subject: string): Promise<Body> =>
        (await (await api(path, subject, 'application/vnd.github+json')).json()) as Body;

    const readFile = async (commit: string, file: string, attempt = 1): Promise<string> => {
        const response = await request(`https://raw.githubusercontent.com/${repository}/${commit}/${prefix}${file}`).catch(
            () => /* A request without an answer is asked again like one with a bad answer. */ null,
        );

        if (response?.ok) {
            return textOf(response);
        }

        if (attempt < READ_ATTEMPTS) {
            return readFile(commit, file, attempt + 1);
        }

        throw new GitHubError(`GitHub did not deliver ${file} of commit ${commit}.`);
    };

    return {
        async comparisonOfPullRequest(number) {
            const subject = `pull request #${number}`;
            const pullRequest = await json<PullRequest>(`pulls/${number}`, subject);
            const head = pullRequest.head.sha;
            // The changed files are only on the first page, and the merge base is on every page.
            const comparison = await json<{ merge_base_commit: { sha: string } }>(
                `compare/${encodeRevision(pullRequest.base.ref)}...${head}?per_page=1&page=2`,
                subject,
            );

            return { base: comparison.merge_base_commit.sha, head };
        },

        async commitOf(revision) {
            if (COMMIT_HASH.test(revision)) {
                return revision;
            }

            const response = await api(
                `commits/${encodeRevision(revision)}`,
                `the revision ${revision}`,
                'application/vnd.github.sha',
            );

            return (await response.text()).trim();
        },

        async listFiles(commit): Promise<FileList> {
            const { tree, truncated } = await json<Tree>(`git/trees/${commit}?recursive=1`, `commit ${commit}`);

            if (truncated) {
                throw new GitHubError(`GitHub cut the file list of commit ${commit} short.`);
            }

            return new Map(
                tree
                    .filter(({ type, path }) => type === 'blob' && path.startsWith(prefix))
                    .map(({ path, sha }): [string, string] => [path.slice(prefix.length), sha]),
            );
        },

        readFile: (commit, file) => readFile(commit, file),

        sourceUrl(commit, at) {
            const separator = at.lastIndexOf(':');

            return `https://github.com/${repository}/blob/${commit}/${prefix}${at.slice(0, separator)}#L${at.slice(separator + 1)}`;
        },
    };
}

/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {
    createPullRequestResolver,
    GitRefError,
    parseGitHubRemoteUrl,
    parsePullRequestInput,
    parsePullRequestRef,
    resolveRef,
    type FetchLike,
    type PullRequestResolverOptions,
} from '.';
import { createPullRequestRemote, createTestRepo, type PullRequestRemote, type TestRepo } from './test-helpers';

interface ApiCall {
    url: string;
    headers: Record<string, string>;
}

function fakeApi(answer: (call: ApiCall) => unknown) {
    const calls: ApiCall[] = [];
    const fetch: FetchLike = (url, { headers }) => {
        calls.push({ url, headers });

        try {
            const body = answer({ url, headers });

            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
        } catch (error) {
            return Promise.reject(error as Error);
        }
    };

    return { fetch, calls };
}

describe('api-explorer git pull requests', () => {
    describe('parsePullRequestInput', () => {
        it.each([
            ['19666', { number: 19666 }],
            ['#19666', { number: 19666 }],
            [' 19666 ', { number: 19666 }],
            ['pr:19666', { number: 19666 }],
            ['https://github.com/shopware/shopware/pull/19666', { owner: 'shopware', repo: 'shopware', number: 19666 }],
            [
                'https://github.com/Shopware/Shopware/pull/19666/files?w=1',
                { owner: 'shopware', repo: 'shopware', number: 19666 },
            ],
            ['shopware/shopware/19666', { owner: 'shopware', repo: 'shopware', number: 19666 }],
            ['shopware/shopware#19666', { owner: 'shopware', repo: 'shopware', number: 19666 }],
            ['my-org/admin.ext_1/pull/3', { owner: 'my-org', repo: 'admin.ext_1', number: 3 }],
        ])('parses %j', (input, expected) => {
            expect(parsePullRequestInput(input)).toEqual(expected);
        });

        it.each([
            '',
            '0',
            '1;rm',
            '../x',
            '../../1',
            'a/b/c/1',
            'a$b/c/1',
            '.git/x/1',
            'x/y.lock/1',
            'https://example.com/a/b/pull/1',
            '99999999999',
        ])('rejects %j', (input) => {
            expect(() => parsePullRequestInput(input)).toThrow(GitRefError);
        });

        it('tells pull request refs apart from other refs', () => {
            expect(parsePullRequestRef('pr:7')).toEqual({ side: 'head', input: { number: 7 } });
            expect(parsePullRequestRef('pr-base:acme/shop/7')).toEqual({
                side: 'base',
                input: { owner: 'acme', repo: 'shop', number: 7 },
            });
            expect(parsePullRequestRef('trunk')).toBeNull();
            expect(parsePullRequestRef('origin/pr-7')).toBeNull();
        });

        it.each([
            ['git@github.com:shopware/shopware.git', { owner: 'shopware', repo: 'shopware' }],
            ['https://github.com/shopware/shopware.git', { owner: 'shopware', repo: 'shopware' }],
            ['https://token@github.com/Acme/Shop', { owner: 'acme', repo: 'shop' }],
            ['ssh://git@github.com/acme/shop.git/', { owner: 'acme', repo: 'shop' }],
            ['git@gitlab.com:acme/shop.git', null],
            ['/srv/git/shop.git', null],
        ])('reads the GitHub repository of the remote url %j', (url, expected) => {
            expect(parseGitHubRemoteUrl(url)).toEqual(expected);
        });
    });

    describe('resolution', () => {
        let repo: TestRepo;
        let remote: PullRequestRemote;
        let remoteUrl: string;

        const pullRequestBody = (headSha: string) => ({
            title: 'Rework the update module',
            state: 'closed',
            merged: true,
            html_url: 'https://github.com/acme/shop/pull/7',
            base: { ref: 'trunk' },
            head: { sha: headSha },
        });

        function resolver(options: PullRequestResolverOptions = {}) {
            return createPullRequestResolver({ repositoryUrl: () => remoteUrl, env: {}, ...options });
        }

        function refsOutsideNamespace(): string {
            return repo
                .git('for-each-ref', '--format=%(refname) %(objectname) %(symref)')
                .split('\n')
                .filter((line) => !line.startsWith('refs/api-explorer/'))
                .join('\n');
        }

        function namespaceRefs(): string {
            return repo.git('for-each-ref', '--format=%(refname) %(objectname)', 'refs/api-explorer');
        }

        beforeEach(() => {
            repo = createTestRepo();
            remote = createPullRequestRemote(repo);
            remoteUrl = remote.url;
            repo.git('config', 'remote.origin.url', 'git@github.com:Acme/Shop.git');
        });

        afterEach(() => {
            remote.cleanup();
            repo.cleanup();
        });

        it('fetches the head and base branch into its own namespace and changes nothing else', async () => {
            const refsBefore = refsOutsideNamespace();
            const headBefore = repo.git('rev-parse', 'HEAD');
            const api = fakeApi(() => pullRequestBody(remote.head));

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution).toEqual({
                owner: 'acme',
                repo: 'shop',
                number: 7,
                title: 'Rework the update module',
                state: 'closed',
                merged: true,
                url: 'https://github.com/acme/shop/pull/7',
                baseRef: 'trunk',
                headSha: remote.head,
                baseSha: remote.baseTip,
                mergeBaseSha: repo.second,
                warnings: [],
            });
            expect(api.calls.map(({ url }) => url)).toEqual(['https://api.github.com/repos/acme/shop/pulls/7']);
            expect(namespaceRefs()).toBe(
                [
                    `refs/api-explorer/pull/acme/shop/7/base ${remote.baseTip}`,
                    `refs/api-explorer/pull/acme/shop/7/head ${remote.head}`,
                ].join('\n'),
            );
            expect(refsOutsideNamespace()).toBe(refsBefore);
            expect(repo.git('rev-parse', 'HEAD')).toBe(headBefore);
            expect(repo.git('status', '--porcelain')).toBe('');
            expect(fs.existsSync(path.join(repo.repoRoot, '.git', 'FETCH_HEAD'))).toBe(false);
        });

        it('resolves pr: to the head and pr-base: to the merge base with the base branch', async () => {
            const pullRequests = resolver({ fetch: fakeApi(() => pullRequestBody(remote.head)).fetch });

            await expect(resolveRef(repo.repoRoot, 'pr:7', { pullRequests })).resolves.toBe(remote.head);
            await expect(resolveRef(repo.repoRoot, 'pr-base:7', { pullRequests })).resolves.toBe(repo.second);
            await expect(resolveRef(repo.repoRoot, 'pr:acme/shop/7', { pullRequests })).resolves.toBe(remote.head);
            await expect(
                resolveRef(repo.repoRoot, 'pr-base:https://github.com/acme/shop/pull/7', { pullRequests }),
            ).resolves.toBe(repo.second);
        });

        it('sends the GitHub token from the environment', async () => {
            const api = fakeApi(() => pullRequestBody(remote.head));

            await resolver({ fetch: api.fetch, env: { GH_TOKEN: 'secret' } }).resolve(repo.repoRoot, { number: 7 });

            expect(api.calls[0].headers).toMatchObject({ Authorization: 'Bearer secret' });
        });

        it('falls back to trunk when the GitHub API is unavailable', async () => {
            const api = fakeApi(() => {
                throw new Error('getaddrinfo ENOTFOUND api.github.com');
            });

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution).toMatchObject({
                title: null,
                state: null,
                merged: null,
                url: 'https://github.com/acme/shop/pull/7',
                baseRef: 'trunk',
                headSha: remote.head,
                mergeBaseSha: repo.second,
                warnings: [
                    'GitHub API unavailable (getaddrinfo ENOTFOUND api.github.com): assumed base branch trunk, title unknown.',
                ],
            });
        });

        it('treats an unexpected API answer like an unavailable API', async () => {
            const api = fakeApi(() => ({ ...pullRequestBody(remote.head), head: { sha: 'not-a-sha' } }));

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution.baseRef).toBe('trunk');
            expect(resolution.warnings[0]).toContain('unexpected response');
        });

        it.each([
            'fix+x',
            'release/a@b',
        ])('fetches the base branch %j with every valid branch name', async (branch) => {
            repo.git('push', '--quiet', remote.url, `${repo.second}:refs/heads/${branch}`);
            const api = fakeApi(() => ({ ...pullRequestBody(remote.head), base: { ref: branch } }));

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution).toMatchObject({
                title: 'Rework the update module',
                baseRef: branch,
                baseSha: repo.second,
                mergeBaseSha: repo.second,
                warnings: [],
            });
        });

        it.each([
            '--upload-pack=evil',
            '+refs/heads/x',
            'a:b',
            'a..b',
            'a@{-1}',
        ])('refuses the base branch %j instead of falling back to trunk', async (branch) => {
            const api = fakeApi(() => ({ ...pullRequestBody(remote.head), base: { ref: branch } }));

            await expect(resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 })).rejects.toThrow(
                new GitRefError(
                    `The base branch ${JSON.stringify(branch)} of pull request #7 of acme/shop is no branch name that can be fetched`,
                ),
            );
            expect(namespaceRefs()).toBe('');
        });

        it.each([
            'merge',
            'squash',
        ] as const)('finds the fork point of a pull request merged with a %s commit', async (mode) => {
            const mergeCommit = remote.mergePullRequest(mode);
            const api = fakeApi(() => ({ ...pullRequestBody(remote.head), merge_commit_sha: mergeCommit }));

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution).toMatchObject({
                headSha: remote.head,
                baseSha: mergeCommit,
                mergeBaseSha: repo.second,
                warnings: [],
            });
            expect(namespaceRefs()).toContain(`refs/api-explorer/pull/acme/shop/7/merge ${mergeCommit}`);
        });

        it('keeps comparing open pull requests with the tip of the base branch', async () => {
            const mergeCommit = remote.mergePullRequest('merge');
            const api = fakeApi(() => ({
                ...pullRequestBody(remote.head),
                state: 'open',
                merged: false,
                merge_commit_sha: mergeCommit,
            }));

            const resolution = await resolver({ fetch: api.fetch }).resolve(repo.repoRoot, { number: 7 });

            expect(resolution.mergeBaseSha).toBe(remote.head);
            expect(resolution.warnings).toEqual(['The head is already part of trunk, so the comparison is empty.']);
        });

        describe('in a shallow clone', () => {
            const mergeBaseAnswer = (sha: string, date: string) => ({
                merge_base_commit: { sha, commit: { committer: { date } } },
            });

            it('refuses a pull request that forks before the history starts without downloading it', async () => {
                const clone = remote.shallowClone();
                const shallowBefore = clone.git('rev-parse', '--git-path', 'shallow');
                const shallowContent = fs.readFileSync(path.resolve(clone.repoRoot, shallowBefore), 'utf8');
                const api = fakeApi(({ url }) =>
                    url.includes('/compare/')
                        ? mergeBaseAnswer(repo.second, '2020-01-01T00:00:00Z')
                        : pullRequestBody(remote.head),
                );

                await expect(resolver({ fetch: api.fetch }).resolve(clone.repoRoot, { number: 7 })).rejects.toThrow(
                    /^The repository is a shallow clone whose history starts at \S+; pull request #7 of acme\/shop forks before that \(its merge base is from 2020-01-01T00:00:00Z\)\. Run `git fetch --unshallow` to compare it\.$/,
                );
                expect(api.calls.map(({ url }) => url)).toEqual([
                    'https://api.github.com/repos/acme/shop/pulls/7',
                    `https://api.github.com/repos/acme/shop/compare/trunk...${remote.head}`,
                ]);
                expect(clone.git('for-each-ref', 'refs/api-explorer')).toBe('');
                expect(() => clone.git('cat-file', '-e', `${remote.head}^{commit}`)).toThrow();
                expect(fs.readFileSync(path.resolve(clone.repoRoot, shallowBefore), 'utf8')).toBe(shallowContent);
            });

            it('looks up the fork point of a merged pull request from the commit before the merge', async () => {
                const mergeCommit = remote.mergePullRequest('merge');
                const clone = remote.shallowClone();
                const api = fakeApi(({ url }) => {
                    if (url.includes('/commits/')) {
                        return { parents: [{ sha: remote.baseTip }] };
                    }

                    return url.includes('/compare/')
                        ? mergeBaseAnswer(repo.second, '2020-01-01T00:00:00Z')
                        : { ...pullRequestBody(remote.head), merge_commit_sha: mergeCommit };
                });

                await expect(resolver({ fetch: api.fetch }).resolve(clone.repoRoot, { number: 7 })).rejects.toThrow(
                    /shallow clone/,
                );
                expect(api.calls.map(({ url }) => url)).toEqual([
                    'https://api.github.com/repos/acme/shop/pulls/7',
                    `https://api.github.com/repos/acme/shop/commits/${mergeCommit}`,
                    `https://api.github.com/repos/acme/shop/compare/${remote.baseTip}...${remote.head}`,
                ]);
            });

            it('resolves a pull request that forks inside the history', async () => {
                const head = remote.openPullRequestOnTrunk(9);
                const clone = remote.shallowClone();
                const api = fakeApi(({ url }) =>
                    url.includes('/compare/')
                        ? mergeBaseAnswer(remote.baseTip, new Date().toISOString())
                        : pullRequestBody(head),
                );

                await expect(resolver({ fetch: api.fetch }).resolve(clone.repoRoot, { number: 9 })).resolves.toMatchObject({
                    headSha: head,
                    mergeBaseSha: remote.baseTip,
                });
                expect(clone.git('rev-parse', '--is-shallow-repository')).toBe('true');
            });

            it('reports a pull request the GitHub API does not know without fetching', async () => {
                const clone = remote.shallowClone();
                const fetch: FetchLike = () => Promise.resolve({ ok: false, status: 404, json: () => Promise.resolve({}) });

                await expect(resolver({ fetch }).resolve(clone.repoRoot, { number: 8 })).rejects.toThrow(
                    new GitRefError('Unknown pull request #8 of acme/shop'),
                );
                expect(clone.git('for-each-ref', 'refs/api-explorer')).toBe('');
            });

            it('refuses to fetch when the GitHub API cannot tell the fork point', async () => {
                const clone = remote.shallowClone();
                const api = fakeApi(() => {
                    throw new Error('offline');
                });

                await expect(resolver({ fetch: api.fetch }).resolve(clone.repoRoot, { number: 7 })).rejects.toThrow(
                    /shallow clone .* and the GitHub API is unavailable/,
                );
                expect(clone.git('for-each-ref', 'refs/api-explorer')).toBe('');
            });
        });

        it('caches resolutions for a while and fetches again on refresh or expiry', async () => {
            let now = 0;
            const api = fakeApi(() => pullRequestBody(remote.head));
            const pullRequests = resolver({ fetch: api.fetch, now: () => now, cacheTtlMs: 1000 });

            await pullRequests.resolve(repo.repoRoot, { number: 7 });
            const pushed = remote.pushToPullRequest();
            now = 999;

            await expect(pullRequests.resolve(repo.repoRoot, { number: 7 })).resolves.toMatchObject({
                headSha: remote.head,
            });
            expect(api.calls).toHaveLength(1);

            await expect(pullRequests.resolve(repo.repoRoot, { number: 7 }, { refresh: true })).resolves.toMatchObject({
                headSha: pushed,
                warnings: [`GitHub reports head ${remote.head}, fetched ${pushed}.`],
            });

            now = 5000;
            await pullRequests.resolve(repo.repoRoot, { number: 7 });
            expect(api.calls).toHaveLength(3);
        });

        it('reports an unknown pull request', async () => {
            const pullRequests = resolver({ fetch: fakeApi(() => pullRequestBody(remote.head)).fetch });

            await expect(resolveRef(repo.repoRoot, 'pr:8', { pullRequests })).rejects.toThrow(
                new GitRefError('Unknown pull request #8 of acme/shop'),
            );
            expect(namespaceRefs()).toBe('');
        });

        it('keeps working with the refs fetched earlier when the remote is unreachable', async () => {
            const pullRequests = resolver({ fetch: fakeApi(() => pullRequestBody(remote.head)).fetch, cacheTtlMs: 0 });
            await pullRequests.resolve(repo.repoRoot, { number: 7 });
            remoteUrl = path.join(repo.repoRoot, 'missing.git');

            const resolution = await pullRequests.resolve(repo.repoRoot, { number: 7 });

            expect(resolution.headSha).toBe(remote.head);
            expect(resolution.warnings).toEqual([
                expect.stringMatching(/^Fetching failed .*; using the refs fetched earlier\.$/s),
            ]);
        });

        it('needs owner and repo when origin is no GitHub repository', async () => {
            repo.git('config', 'remote.origin.url', 'git@gitlab.com:acme/shop.git');
            const pullRequests = resolver({ fetch: fakeApi(() => pullRequestBody(remote.head)).fetch });

            await expect(resolveRef(repo.repoRoot, 'pr:7', { pullRequests })).rejects.toThrow(/<owner>\/<repo>\/<n>/);
            await expect(resolveRef(repo.repoRoot, 'pr:acme/shop/7', { pullRequests })).resolves.toBe(remote.head);
        });

        it.each([
            'pr:1;rm',
            'pr:../x',
            'pr:a/b/c/1',
            'pr-base:-x/y/1',
            'pr:',
        ])('rejects %j before running git or calling the API', async (ref) => {
            const api = fakeApi(() => pullRequestBody(remote.head));

            await expect(resolveRef(repo.repoRoot, ref, { pullRequests: resolver({ fetch: api.fetch }) })).rejects.toThrow(
                GitRefError,
            );
            expect(api.calls).toEqual([]);
            expect(namespaceRefs()).toBe('');
        });
    });
});

/**
 * @sw-package framework
 */
import { GitHubError, createGitHub } from './github';
import type { HttpResponse } from './http';
import {
    ADMINISTRATION,
    BASE_COMMIT,
    HEAD_COMMIT,
    PULL_REQUEST,
    REPOSITORY,
    fixtureCase,
    fixtureGitHub,
} from './testing/fixture-github';
import { respond } from './testing/http-response';

const API = `https://api.github.com/repos/${REPOSITORY}`;
const RAW = `https://raw.githubusercontent.com/${REPOSITORY}`;
const TEMPLATE = 'src/app/component/base/sw-example-state-card/sw-example-state-card.html.twig';

describe('createGitHub', () => {
    const blockMoved = fixtureCase('block-moved');

    function gitHubWith(overrides?: (url: string) => HttpResponse | undefined, token?: string) {
        const served = fixtureGitHub(blockMoved, overrides);

        return {
            served,
            github: createGitHub({ repository: REPOSITORY, administration: ADMINISTRATION, fetch: served.fetch, token }),
        };
    }

    it('compares the head of a pull request with the commit it branched off its base branch', async () => {
        const { github } = gitHubWith();

        await expect(github.comparisonOfPullRequest(PULL_REQUEST)).resolves.toEqual({
            base: BASE_COMMIT,
            head: HEAD_COMMIT,
        });
    });

    it('asks GitHub for the commit of a branch or a tag, and takes a hash as it is', async () => {
        const { github, served } = gitHubWith((url) =>
            url === `${API}/commits/release/6.7` ? respond(`${HEAD_COMMIT}\n`) : undefined,
        );

        await expect(github.commitOf('release/6.7')).resolves.toBe(HEAD_COMMIT);
        await expect(github.commitOf(BASE_COMMIT)).resolves.toBe(BASE_COMMIT);
        expect(served.requests).toEqual([`${API}/commits/release/6.7`]);
    });

    it('lists the files of the Administration by their path below it', async () => {
        const { github } = gitHubWith();

        const files = await github.listFiles(BASE_COMMIT);

        expect([...files.keys()].sort()).toEqual([
            'src/app/component/base/sw-example-state-card/index.js',
            TEMPLATE,
            'src/app/component/example.spec.js',
            'src/app/component/index.ts',
        ]);
    });

    it('refuses a file list that GitHub cut short', async () => {
        const { github } = gitHubWith((url) =>
            url.includes('/git/trees/') ? respond({ truncated: true, tree: [] }) : undefined,
        );

        await expect(github.listFiles(BASE_COMMIT)).rejects.toThrow('GitHub cut the file list');
    });

    it('reads a file of a commit and keeps its byte order mark', async () => {
        const { github } = gitHubWith((url) =>
            url === `${RAW}/${HEAD_COMMIT}/${ADMINISTRATION}/src/bom.js` ? respond('﻿export default {};') : undefined,
        );

        await expect(github.readFile(HEAD_COMMIT, 'src/bom.js')).resolves.toBe('﻿export default {};');
        await expect(github.readFile(HEAD_COMMIT, TEMPLATE)).resolves.toContain('{% block sw_example_state_card %}');
    });

    it('asks again when a file does not arrive', async () => {
        let failures = 2;
        const { github } = gitHubWith((url) => {
            if (!url.startsWith(RAW) || failures === 0) {
                return undefined;
            }

            failures -= 1;

            return respond('', 502);
        });

        await expect(github.readFile(HEAD_COMMIT, TEMPLATE)).resolves.toContain('{% block');
    });

    it('names the file that GitHub keeps failing to deliver', async () => {
        const { github } = gitHubWith((url) => (url.startsWith(RAW) ? respond('', 502) : undefined));

        await expect(github.readFile(HEAD_COMMIT, TEMPLATE)).rejects.toThrow(
            new GitHubError(`GitHub did not deliver ${TEMPLATE} of commit ${HEAD_COMMIT}.`),
        );
    });

    it('says until when the requests without a login are used up', async () => {
        const reset = new Date(2026, 10, 27, 14, 30);
        const { github } = gitHubWith(() =>
            respond({ message: 'API rate limit exceeded' }, 403, {
                'x-ratelimit-remaining': '0',
                'x-ratelimit-reset': String(reset.getTime() / 1000),
            }),
        );

        await expect(github.comparisonOfPullRequest(PULL_REQUEST)).rejects.toThrow(
            `GitHub allows 60 requests per hour without a login, and they are used up until ${reset.toLocaleTimeString()}.`,
        );
    });

    it('says which pull request GitHub does not know', async () => {
        const { github } = gitHubWith();

        await expect(github.comparisonOfPullRequest(404)).rejects.toThrow('GitHub does not know pull request #404.');
    });

    it('sends the token to the API only', async () => {
        const headers: Array<[string, string | undefined]> = [];
        const served = fixtureGitHub(blockMoved);
        const github = createGitHub({
            repository: REPOSITORY,
            administration: ADMINISTRATION,
            token: 'secret',
            fetch: (url, init) => {
                headers.push([
                    new URL(url).host,
                    init?.headers.Authorization,
                ]);

                return served.fetch(url, init);
            },
        });

        await github.listFiles(BASE_COMMIT);
        await github.readFile(BASE_COMMIT, TEMPLATE);

        expect(headers).toEqual([
            ['api.github.com', 'Bearer secret'],
            ['raw.githubusercontent.com', undefined],
        ]);
    });

    it('links the line a finding names in the commit', () => {
        const { github } = gitHubWith();

        expect(github.sourceUrl(HEAD_COMMIT, `${TEMPLATE}:28`)).toBe(
            `https://github.com/${REPOSITORY}/blob/${HEAD_COMMIT}/${ADMINISTRATION}/${TEMPLATE}#L28`,
        );
    });
});

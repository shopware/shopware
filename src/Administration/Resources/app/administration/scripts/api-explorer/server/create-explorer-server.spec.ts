/**
 * @sw-package framework
 */

import { once } from 'node:events';
import * as http from 'node:http';
import { component, objectNode, snapshot } from '../app/__fixtures__/compare-fixture';
import { createDiff } from '../diff/create-diff';
import { createPullRequestResolver, GitRefError, type PullRequestResolution } from '../git';
import { createPullRequestRemote, createTestRepo } from '../git/test-helpers';
import type { ApiSnapshot } from '../snapshot/types';
import type { CompareResponse, PullRequestResponse, StateResponse } from './api-types';
import {
    createExplorerServer,
    type ExplorerServer,
    type ExplorerSnapshotService,
    type ResolvePullRequest,
} from './create-explorer-server';

const STATUS = {
    repoRoot: '/repo',
    adminRoot: '/repo/src/Administration/Resources/app/administration',
    branch: 'feature/save-and-close',
    head: '84182fe36a97aef4f1f417bb2e46109640b032f8',
    changedFiles: 3,
    worktreeSnapshotCreatedAt: null,
};

const SNAPSHOTS: Record<string, ApiSnapshot> = {
    trunk: snapshot('trunk', objectNode('Shopware', 'object'), [
        component('sw-card'),
        component('sw-button'),
    ]),
    WORKTREE: snapshot('WORKTREE', objectNode('Shopware', 'object'), [component('sw-card')]),
};

const REFS = [
    { name: 'trunk', kind: 'branch' as const, commit: STATUS.head },
    { name: 'v6.7.0.0', kind: 'tag' as const, commit: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' },
];

type SnapshotCall = [string, { refresh?: boolean } | undefined];

function fakeService(overrides: Partial<ExplorerSnapshotService> = {}) {
    const snapshotCalls: SnapshotCall[] = [];
    const service: ExplorerSnapshotService = {
        getSnapshot(ref, options) {
            snapshotCalls.push([
                ref,
                options,
            ]);

            if (!Object.hasOwn(SNAPSHOTS, ref)) {
                return Promise.reject(new GitRefError(`Unknown git ref: ${ref}`));
            }

            return Promise.resolve(SNAPSHOTS[ref]);
        },
        listRefs: () => Promise.resolve(REFS),
        status: () => Promise.resolve(STATUS),
        ...overrides,
    };

    return { service, snapshotCalls };
}

async function get(
    server: ExplorerServer,
    path: string,
    method = 'GET',
    headers: http.OutgoingHttpHeaders = {},
): Promise<{ status: number; type: string; body: unknown }> {
    const request = http.request({ host: '127.0.0.1', port: server.port, path, method, headers });
    request.end();
    const [response] = (await once(request, 'response')) as [http.IncomingMessage];
    let data = '';

    for await (const chunk of response) {
        data += String(chunk);
    }

    return {
        status: response.statusCode ?? 0,
        type: response.headers['content-type'] ?? '',
        body: JSON.parse(data) as unknown,
    };
}

describe('api-explorer/server/create-explorer-server', () => {
    let server: ExplorerServer | null = null;

    async function start(
        service: ExplorerSnapshotService = fakeService().service,
        resolvePullRequest?: ResolvePullRequest,
    ): Promise<ExplorerServer> {
        server = await createExplorerServer({ service, createDiff, port: 0, host: '127.0.0.1', resolvePullRequest });

        return server;
    }

    afterEach(async () => {
        await server?.close();
        server = null;
    });

    it('reports the working tree status, the refs and the default refs', async () => {
        const response = await get(await start(), '/api/state');

        expect(response.status).toBe(200);
        expect(response.type).toContain('application/json');
        expect(response.body).toEqual({
            status: STATUS,
            refs: REFS,
            defaults: { base: 'trunk', head: 'WORKTREE' },
        } satisfies StateResponse);
    });

    it('compares two refs and returns both snapshots, the diff and the timings', async () => {
        const response = await get(await start(), '/api/compare?base=trunk&head=WORKTREE');
        const body = response.body as CompareResponse;

        expect(response.status).toBe(200);
        expect(body.base).toEqual(SNAPSHOTS.trunk);
        expect(body.head).toEqual(SNAPSHOTS.WORKTREE);
        expect(body.diff).toEqual(createDiff(SNAPSHOTS.trunk, SNAPSHOTS.WORKTREE));
        expect(body.diff.changes).toEqual([
            expect.objectContaining({ kind: 'component', owner: 'sw-button', change: 'removed' }),
        ]);
        expect(Object.keys(body.timings).sort()).toEqual([
            'baseMs',
            'diffMs',
            'headMs',
        ]);
        expect(Object.values(body.timings).every((value) => Number.isInteger(value) && value >= 0)).toBe(true);
    });

    it('defaults to trunk against the working tree', async () => {
        const { service, snapshotCalls } = fakeService();
        await get(await start(service), '/api/compare');

        expect(snapshotCalls).toEqual([
            [
                'trunk',
                { refresh: false },
            ],
            [
                'WORKTREE',
                { refresh: false },
            ],
        ]);
    });

    it('passes refresh to the service for the working tree only', async () => {
        const { service, snapshotCalls } = fakeService();
        const response = await get(await start(service), '/api/compare?base=trunk&head=WORKTREE&refresh=1');

        expect(response.status).toBe(200);
        expect(snapshotCalls).toEqual([
            [
                'trunk',
                { refresh: false },
            ],
            [
                'WORKTREE',
                { refresh: true },
            ],
        ]);
    });

    it.each([
        [
            'an unknown ref',
            '/api/compare?base=does-not-exist',
            'Unknown git ref: does-not-exist',
        ],
        [
            'a ref that looks like an option',
            '/api/compare?base=--output%3D%2Ftmp%2Fx',
            'Invalid git ref',
        ],
        [
            'an empty ref',
            '/api/compare?base=&head=WORKTREE',
            'Query parameter "base" must not be empty.',
        ],
    ])('answers 400 for %s', async (_case, path, message) => {
        const response = await get(await start(), path);

        expect(response.status).toBe(400);
        expect((response.body as { error: string }).error).toContain(message);
    });

    it('answers 500 with the message when the extraction fails', async () => {
        jest.spyOn(console, 'error').mockImplementation(() => undefined);
        const { service } = fakeService({ getSnapshot: () => Promise.reject(new Error('tsc crashed')) });
        const response = await get(await start(service), '/api/compare?base=trunk');

        expect(response.status).toBe(500);
        expect(response.body).toEqual({ error: 'tsc crashed' });
    });

    it('answers 404 for unknown endpoints and other paths without a UI', async () => {
        const running = await start();

        expect(await get(running, '/api/unknown')).toEqual({
            status: 404,
            type: 'application/json; charset=utf-8',
            body: { error: 'Unknown endpoint: /api/unknown' },
        });
        expect(await get(running, '/index.html')).toMatchObject({ status: 404 });
    });

    it('answers 405 for methods other than GET', async () => {
        const response = await get(await start(), '/api/state', 'POST');

        expect(response.status).toBe(405);
    });

    it('hands everything outside the API to the UI', async () => {
        let uiClosed = false;
        server = await createExplorerServer({
            service: fakeService().service,
            createDiff,
            port: 0,
            host: '127.0.0.1',
            ui: () =>
                Promise.resolve({
                    middleware: (_request, response) => {
                        response.writeHead(200, { 'Content-Type': 'application/json' });
                        response.end('{"ui":true}');
                    },
                    close: () => {
                        uiClosed = true;
                        return Promise.resolve();
                    },
                }),
        });

        expect(await get(server, '/components')).toMatchObject({ status: 200, body: { ui: true } });

        await server.close();
        server = null;
        expect(uiClosed).toBe(true);
    });

    describe('/api/pull-request', () => {
        const RESOLUTION: PullRequestResolution = {
            owner: 'shopware',
            repo: 'shopware',
            number: 19666,
            title: 'Rework the update module',
            state: 'closed',
            merged: true,
            url: 'https://github.com/shopware/shopware/pull/19666',
            baseRef: 'trunk',
            headSha: 'a'.repeat(40),
            baseSha: 'b'.repeat(40),
            mergeBaseSha: 'c'.repeat(40),
            warnings: [],
        };

        function fakeResolver() {
            const calls: Array<Parameters<ResolvePullRequest>> = [];
            const resolve: ResolvePullRequest = (input, options) => {
                calls.push([
                    input,
                    options,
                ]);

                return Promise.resolve({ ...RESOLUTION, ...input });
            };

            return { resolve, calls };
        }

        it.each([
            [
                '19666',
                'pr:19666',
            ],
            [
                '%2319666',
                'pr:19666',
            ],
            [
                encodeURIComponent('https://github.com/shopware/shopware/pull/19666'),
                'pr:shopware/shopware/19666',
            ],
        ])('describes the pull request %s with the refs to compare', async (pr, head) => {
            const resolver = fakeResolver();
            const response = await get(await start(undefined, resolver.resolve), `/api/pull-request?pr=${pr}`);

            expect(response.status).toBe(200);
            expect(response.body).toEqual({
                number: 19666,
                owner: 'shopware',
                repo: 'shopware',
                title: 'Rework the update module',
                state: 'closed',
                merged: true,
                url: 'https://github.com/shopware/shopware/pull/19666',
                baseRef: 'trunk',
                headSha: 'a'.repeat(40),
                mergeBaseSha: 'c'.repeat(40),
                warnings: [],
                refs: { base: head.replace('pr:', 'pr-base:'), head },
            } satisfies PullRequestResponse);
            expect(resolver.calls[0][1]).toEqual({ refresh: false });
        });

        it('passes refresh to the resolver', async () => {
            const resolver = fakeResolver();
            await get(await start(undefined, resolver.resolve), '/api/pull-request?pr=7&refresh=1');

            expect(resolver.calls).toEqual([
                [
                    { number: 7 },
                    { refresh: true },
                ],
            ]);
        });

        it.each([
            [
                '/api/pull-request',
                'Query parameter "pr" must not be empty.',
            ],
            [
                '/api/pull-request?pr=1%3Brm',
                'Invalid pull request',
            ],
            [
                '/api/pull-request?pr=a%2Fb%2Fc%2F1',
                'Invalid pull request',
            ],
        ])('answers 400 for %s', async (path, message) => {
            const resolver = fakeResolver();
            const response = await get(await start(undefined, resolver.resolve), path);

            expect(response.status).toBe(400);
            expect((response.body as { error: string }).error).toContain(message);
            expect(resolver.calls).toEqual([]);
        });

        it('fetches the pull request from the remote of the repository', async () => {
            const repo = createTestRepo();
            const remote = createPullRequestRemote(repo);

            try {
                repo.git('config', 'remote.origin.url', 'https://github.com/acme/shop.git');
                const resolver = createPullRequestResolver({
                    fetch: () => Promise.reject(new Error('offline')),
                    repositoryUrl: () => remote.url,
                    env: {},
                });
                const response = await get(
                    await start(undefined, (input, options) => resolver.resolve(repo.repoRoot, input, options)),
                    '/api/pull-request?pr=%237',
                );

                expect(response.status).toBe(200);
                expect(response.body).toMatchObject({
                    number: 7,
                    owner: 'acme',
                    repo: 'shop',
                    title: null,
                    baseRef: 'trunk',
                    headSha: remote.head,
                    mergeBaseSha: repo.second,
                    warnings: [expect.stringContaining('GitHub API unavailable (offline)')],
                    refs: { base: 'pr-base:7', head: 'pr:7' },
                });
            } finally {
                remote.cleanup();
                repo.cleanup();
            }
        });

        it('answers 400 for an unknown pull request', async () => {
            const response = await get(
                await start(undefined, () => Promise.reject(new GitRefError('Unknown pull request #8 of acme/shop'))),
                '/api/pull-request?pr=8',
            );

            expect(response).toMatchObject({ status: 400, body: { error: 'Unknown pull request #8 of acme/shop' } });
        });
    });

    describe('request origin', () => {
        it.each([
            ['a foreign Host (DNS rebinding)', { Host: 'evil.example:5175' }],
            ['a Host that only starts like localhost', { Host: 'localhost.evil.example' }],
            ['a foreign Origin', { Origin: 'https://evil.example' }],
            ['an Origin on another local port', { Origin: 'http://localhost:3000', Host: 'localhost:5175' }],
            ['a null Origin', { Origin: 'null' }],
            ['a cross-site fetch', { 'Sec-Fetch-Site': 'cross-site' }],
            ['a same-site fetch from another port', { 'Sec-Fetch-Site': 'same-site' }],
        ])('answers 403 for %s and does not start any work', async (_case, headers) => {
            const { service, snapshotCalls } = fakeService();
            const pullRequestCalls: unknown[] = [];
            const running = await start(service, (input) => {
                pullRequestCalls.push(input);
                return Promise.reject(new Error('must not be called'));
            });

            for (const path of ['/api/compare?base=trunk', '/api/pull-request?pr=7', '/api/state']) {
                expect(await get(running, path, 'GET', headers)).toMatchObject({ status: 403 });
            }

            expect(snapshotCalls).toEqual([]);
            expect(pullRequestCalls).toEqual([]);
        });

        it.each([
            ['the loopback address', {}],
            ['localhost', { Host: 'localhost:5173' }],
            ['the IPv6 loopback address', { Host: '[::1]:5173' }],
            [
                'a same-origin fetch of the explorer page',
                { Host: 'localhost:5173', Origin: 'http://localhost:5173', 'Sec-Fetch-Site': 'same-origin' },
            ],
            ['a page opened directly', { Host: 'localhost:5173', 'Sec-Fetch-Site': 'none' }],
        ])('answers requests from %s', async (_case, headers) => {
            expect(await get(await start(), '/api/state', 'GET', headers)).toMatchObject({ status: 200 });
        });

        it('also accepts the host the server is bound to', async () => {
            server = await createExplorerServer({ service: fakeService().service, createDiff, port: 0, host: '127.0.0.2' });
            const request = (host: string) =>
                new Promise<number>((resolve, reject) => {
                    http.get(
                        { host: '127.0.0.2', port: server!.port, path: '/api/state', headers: { Host: host } },
                        (response) => {
                            response.resume();
                            resolve(response.statusCode ?? 0);
                        },
                    ).once('error', reject);
                });

            await expect(request(`127.0.0.2:${server.port}`)).resolves.toBe(200);
            await expect(request('127.0.0.3')).resolves.toBe(403);
        });
    });
});

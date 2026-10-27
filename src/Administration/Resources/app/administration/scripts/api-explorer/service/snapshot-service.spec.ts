/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { cacheEntries, createTestRepo, shopwareSource, type TestRepo } from '../git/test-helpers';
import { EXTRACTOR_VERSION, SNAPSHOT_FORMAT_VERSION, type ApiSnapshot } from '../snapshot/types';
import { extractRefInChildProcess, type ExtractRefRequest } from './extract-ref';
import { createSnapshotService } from './snapshot-service';
import { createSnapshot } from '../snapshot/create-snapshot';

function fakeSnapshot({ ref, commit }: ExtractRefRequest): ApiSnapshot {
    return {
        formatVersion: SNAPSHOT_FORMAT_VERSION,
        extractorVersion: EXTRACTOR_VERSION,
        ref,
        commit,
        createdAt: new Date().toISOString(),
        shopwareObject: {
            path: 'Shopware',
            name: 'Shopware',
            kind: 'object',
            signature: 'object',
            callSignatures: [],
            typeSource: 'typescript',
            truncated: false,
            children: [],
            visibility: 'public',
            deprecation: null,
            location: null,
        },
        components: [],
        diagnostics: [],
    };
}

describe('api-explorer snapshot service', () => {
    let repo: TestRepo;
    let materializedRoots: string[];
    let extractRef: jest.Mock<Promise<ApiSnapshot>, [ExtractRefRequest]>;

    beforeEach(() => {
        repo = createTestRepo();
        materializedRoots = [];
        extractRef = jest.fn((request: ExtractRefRequest) => {
            materializedRoots.push(request.adminRoot);

            if (!fs.existsSync(path.join(request.adminRoot, 'src/core/shopware.ts'))) {
                return Promise.reject(new Error(`Not materialized: ${request.adminRoot}`));
            }

            return new Promise<ApiSnapshot>((resolve) => {
                setTimeout(() => resolve(fakeSnapshot(request)), 10);
            });
        });
    });

    afterEach(() => {
        repo.cleanup();
    });

    const createService = () => createSnapshotService({ repoRoot: repo.repoRoot, adminRoot: repo.adminRoot, extractRef });

    describe('git refs', () => {
        it('extracts a ref once and serves later requests from the cache', async () => {
            const first = await createService().getSnapshot('v1');

            expect(first).toMatchObject({ ref: 'v1', commit: repo.first, extractorVersion: EXTRACTOR_VERSION });
            expect(extractRef).toHaveBeenCalledTimes(1);
            expect(cacheEntries(repo)).toEqual([
                expect.stringMatching(new RegExp(`^${repo.first}-${EXTRACTOR_VERSION}-[0-9a-f]{12}\\.json$`)),
            ]);

            const second = await createService().getSnapshot('v1');

            expect(second).toEqual(first);
            expect(extractRef).toHaveBeenCalledTimes(1);
        });

        it('shares the cache between refs of the same commit and labels the snapshot with the requested ref', async () => {
            const service = createService();
            await service.getSnapshot('feature');

            await expect(service.getSnapshot(repo.first.slice(0, 8))).resolves.toMatchObject({
                ref: repo.first.slice(0, 8),
                commit: repo.first,
            });
            expect(extractRef).toHaveBeenCalledTimes(1);
        });

        it('extracts again on refresh', async () => {
            const service = createService();
            await service.getSnapshot('v1');
            await service.getSnapshot('v1', { refresh: true });

            expect(extractRef).toHaveBeenCalledTimes(2);
        });

        it('de-duplicates concurrent requests for the same commit', async () => {
            const service = createService();

            const snapshots = await Promise.all([
                service.getSnapshot('v1'),
                service.getSnapshot('feature'),
                service.getSnapshot('v1', { refresh: true }),
                service.getSnapshot('trunk'),
            ]);

            expect(snapshots.map(({ ref, commit }) => [ref, commit])).toEqual([
                ['v1', repo.first],
                ['feature', repo.first],
                ['v1', repo.first],
                ['trunk', repo.second],
            ]);
            expect(extractRef).toHaveBeenCalledTimes(2);
        });

        it('removes the materialized tree after extraction, also when it fails', async () => {
            const service = createService();
            await service.getSnapshot('v1');

            extractRef.mockImplementationOnce((request) => {
                materializedRoots.push(request.adminRoot);
                return Promise.reject(new Error('extraction failed'));
            });

            await expect(service.getSnapshot('trunk')).rejects.toThrow('extraction failed');
            expect(cacheEntries(repo).filter((file) => file.startsWith(repo.second))).toEqual([]);

            expect(materializedRoots).toHaveLength(2);
            materializedRoots.forEach((root) => expect(fs.existsSync(root)).toBe(false));
        });

        it('rejects unknown refs without extracting', async () => {
            await expect(createService().getSnapshot('nope')).rejects.toThrow('Unknown git ref: nope');
            await expect(createService().getSnapshot('--help')).rejects.toThrow('Invalid git ref');
            expect(extractRef).not.toHaveBeenCalled();
        });

        it('extracts in a child process by default', async () => {
            const service = createSnapshotService({ repoRoot: repo.repoRoot, adminRoot: repo.adminRoot });

            await expect(service.getSnapshot('v1')).resolves.toMatchObject({
                ref: 'v1',
                commit: repo.first,
                extractorVersion: EXTRACTOR_VERSION,
                formatVersion: SNAPSHOT_FORMAT_VERSION,
            });
            expect(cacheEntries(repo)).toHaveLength(1);
        }, 120_000);

        it('reports failing child processes with their output', async () => {
            await expect(
                extractRefInChildProcess({ ref: 'v1', commit: repo.first, adminRoot: path.join(repo.repoRoot, 'missing') }),
            ).rejects.toThrow(/Extracting v1 \([0-9a-f]{40}\) failed with exit code 1:\n.*Admin root is not a directory/s);
        }, 120_000);
    });

    describe('extraction environment', () => {
        function withoutLabels(snapshot: ApiSnapshot): Omit<ApiSnapshot, 'ref' | 'commit' | 'createdAt'> {
            const labelled: Partial<ApiSnapshot> = { ...snapshot };
            delete labelled.ref;
            delete labelled.commit;
            delete labelled.createdAt;

            return labelled as Omit<ApiSnapshot, 'ref' | 'commit' | 'createdAt'>;
        }

        it('extracts a materialized HEAD exactly like the clean working tree', async () => {
            const service = createSnapshotService({
                repoRoot: repo.repoRoot,
                adminRoot: repo.adminRoot,
                extractRef: ({ ref, commit, adminRoot }) => createSnapshot({ adminRoot, ref, commit }),
            });
            const worktree = await service.getSnapshot('WORKTREE');
            const head = await service.getSnapshot('HEAD');

            expect(JSON.stringify(worktree.shopwareObject)).toContain('GeneratedEntity');
            expect(withoutLabels(head)).toEqual(withoutLabels(worktree));
        }, 60_000);

        it.each([
            ['an ignored declaration', 'src/generated.d.ts', 'declare interface GeneratedEntity { id: number }\n'],
            ['the package lock', 'package-lock.json', '{"lockfileVersion": 3}\n'],
        ])('extracts a cached ref again after %s changed', async (_case, file, content) => {
            const service = createService();
            await service.getSnapshot('v1');
            await service.getSnapshot('v1');

            expect(extractRef).toHaveBeenCalledTimes(1);

            repo.write(file, content);
            await service.getSnapshot('v1');

            expect(extractRef).toHaveBeenCalledTimes(2);
            expect(cacheEntries(repo)).toHaveLength(2);
        });
    });

    describe('working tree', () => {
        it('reuses the last snapshot until a refresh picks up changes', async () => {
            const service = createService();

            const [
                first,
                concurrent,
            ] = await Promise.all([
                service.getSnapshot('WORKTREE'),
                service.getSnapshot('WORKTREE'),
            ]);

            expect(first).toMatchObject({ ref: 'WORKTREE', commit: null, extractorVersion: EXTRACTOR_VERSION });
            expect(concurrent).toBe(first);
            await expect(service.getSnapshot('WORKTREE')).resolves.toBe(first);

            repo.write('src/core/shopware.ts', shopwareSource(3));
            await new Promise((resolve) => {
                setTimeout(resolve, 5);
            });

            const refreshed = await service.getSnapshot('WORKTREE', { refresh: true });

            expect(refreshed).not.toBe(first);
            expect(refreshed).toMatchObject({ ref: 'WORKTREE', commit: null });
            expect(Date.parse(refreshed.createdAt)).toBeGreaterThan(Date.parse(first.createdAt));
            await expect(service.getSnapshot('WORKTREE')).resolves.toBe(refreshed);
            expect(extractRef).not.toHaveBeenCalled();
        }, 60_000);

        it('joins refreshes requested before a pending one has started', async () => {
            const service = createService();
            const initial = await service.getSnapshot('WORKTREE');

            const refreshes = await Promise.all([
                service.getSnapshot('WORKTREE', { refresh: true }),
                service.getSnapshot('WORKTREE', { refresh: true }),
                service.getSnapshot('WORKTREE'),
            ]);

            expect(refreshes[0]).not.toBe(initial);
            expect(refreshes[1]).toBe(refreshes[0]);
            expect(refreshes[2]).toBe(refreshes[0]);
        }, 60_000);

        it('shows a changed component after a refresh and keeps the entries of unchanged ones', async () => {
            repo.write(
                'types/shopware.d.ts',
                'declare const Shopware: { Component: { register(name: string, loader: () => Promise<unknown>): void } };\n',
            );
            repo.write(
                'src/module/index.js',
                [
                    "Shopware.Component.register('sw-one', () => import('./sw-one'));",
                    "Shopware.Component.register('sw-two', () => import('./sw-two'));",
                    '',
                ].join('\n'),
            );
            repo.write('src/module/sw-one/index.js', 'export default {\n    methods: {\n        save() {},\n    },\n};\n');
            repo.write('src/module/sw-two/index.js', 'export default {\n    props: ["label"],\n};\n');

            const service = createService();
            const first = await service.getSnapshot('WORKTREE');

            repo.write('src/module/sw-one/index.js', 'export default {\n    methods: {\n        cancel() {},\n    },\n};\n');

            const refreshed = await service.getSnapshot('WORKTREE', { refresh: true });
            const byName = (snapshot: ApiSnapshot, name: string) =>
                snapshot.components.find((component) => component.name === name);

            expect(byName(refreshed, 'sw-one')?.methods.map(({ name }) => name)).toEqual(['cancel']);
            expect(byName(refreshed, 'sw-two')).toBe(byName(first, 'sw-two'));
        }, 60_000);

        it('recovers after a failed refresh', async () => {
            const service = createService();
            await service.getSnapshot('WORKTREE');

            fs.rmSync(path.join(repo.adminRoot, 'tsconfig.json'));
            await expect(service.getSnapshot('WORKTREE', { refresh: true })).rejects.toThrow('tsconfig.json');

            repo.git('checkout', '--quiet', '--', '.');

            await expect(service.getSnapshot('WORKTREE')).resolves.toMatchObject({ ref: 'WORKTREE', commit: null });
        }, 60_000);
    });

    it('reports the status of the checkout and the working tree snapshot', async () => {
        const service = createService();
        repo.write('src/new-file.ts', 'export {};\n');

        await expect(service.status()).resolves.toEqual({
            repoRoot: repo.repoRoot,
            adminRoot: repo.adminRoot,
            branch: 'trunk',
            head: repo.second,
            changedFiles: 1,
            worktreeSnapshotCreatedAt: null,
        });

        const snapshot = await service.getSnapshot('WORKTREE');

        await expect(service.status()).resolves.toMatchObject({ worktreeSnapshotCreatedAt: snapshot.createdAt });
        await expect(service.listRefs()).resolves.toContainEqual({ name: 'v1', kind: 'tag', commit: repo.first });
    }, 60_000);
});

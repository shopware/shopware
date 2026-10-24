/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { cacheEntries, createTestRepo, shopwareSource, type TestRepo } from '../git/test-helpers';
import { EXTRACTOR_VERSION, type ApiSnapshot } from '../snapshot/types';
import { snapshotCommand } from './snapshot';
import { UsageError } from './types';

async function run(...args: string[]): Promise<ApiSnapshot> {
    let stdout = '';
    const exitCode = await snapshotCommand.run(args, {
        stdout: (text) => {
            stdout += text;
        },
        stderr: () => {},
    });

    expect(exitCode).toBe(0);

    return JSON.parse(stdout) as ApiSnapshot;
}

describe('api-explorer snapshot command', () => {
    let repo: TestRepo;

    beforeAll(() => {
        repo = createTestRepo();
    });

    afterAll(() => {
        repo.cleanup();
    });

    it('extracts a git ref through the cache and re-extracts it with --no-cache', async () => {
        const extracted = await run('--root', repo.adminRoot, '--ref', 'v1');

        expect(extracted).toMatchObject({ ref: 'v1', commit: repo.first, extractorVersion: EXTRACTOR_VERSION });
        expect(cacheEntries(repo)).toEqual([
            expect.stringMatching(new RegExp(`^${repo.first}-${EXTRACTOR_VERSION}-[0-9a-f]{12}\\.json$`)),
        ]);

        const cached = await run('--root', repo.adminRoot, '--ref', 'feature');

        expect(cached).toEqual({ ...extracted, ref: 'feature' });

        const reextracted = await run('--root', repo.adminRoot, '--ref', 'v1', '--no-cache');

        expect(reextracted).toMatchObject({ ref: 'v1', commit: repo.first });
        expect(reextracted.createdAt).not.toBe(extracted.createdAt);
    }, 240_000);

    it('extracts --root as is and only records --ref and --commit as labels', async () => {
        repo.write('src/core/shopware.ts', shopwareSource(3));

        try {
            await expect(run('--root', repo.adminRoot, '--ref', 'v1', '--commit', repo.first)).resolves.toMatchObject({
                ref: 'v1',
                commit: repo.first,
            });
        } finally {
            repo.git('checkout', '--quiet', '--', '.');
        }
    }, 60_000);

    it('keeps the working tree as default', async () => {
        await expect(run('--root', repo.adminRoot)).resolves.toMatchObject({ ref: 'WORKTREE', commit: null });
    }, 60_000);

    it.each([
        [
            ['--commit', 'a'.repeat(40)],
            '--commit requires a --ref other than WORKTREE',
        ],
        [
            [
                '--ref',
                'v1',
                '--commit',
                'a'.repeat(40),
                '--no-cache',
            ],
            '--commit and --no-cache cannot be combined',
        ],
    ])('rejects %j', async (args, message) => {
        await expect(snapshotCommand.run(args, { stdout: () => {}, stderr: () => {} })).rejects.toThrow(
            new UsageError(message),
        );
    });

    it('fails for unknown refs', async () => {
        await expect(
            snapshotCommand.run(
                [
                    '--root',
                    repo.adminRoot,
                    '--ref',
                    'nope',
                    '--out',
                    path.join(repo.repoRoot, 'out.json'),
                ],
                {
                    stdout: () => {},
                    stderr: () => {},
                },
            ),
        ).rejects.toThrow('Unknown git ref: nope');
    });
});

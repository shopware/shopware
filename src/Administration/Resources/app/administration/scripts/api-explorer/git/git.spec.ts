/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {
    compareVersionsDescending,
    getWorktreeStatus,
    GitRefError,
    listIgnoredDeclarationFiles,
    listRefs,
    materializeRef,
    resolveRef,
} from '.';
import { createTestRepo, shopwareSource, TEST_ADMIN_PATH, type TestRepo } from './test-helpers';

describe('api-explorer git', () => {
    let repo: TestRepo;

    beforeAll(() => {
        repo = createTestRepo();
    });

    afterAll(() => {
        repo.cleanup();
    });

    describe('listRefs', () => {
        it('lists local branches, remote branches and tags by version descending with their commits', async () => {
            await expect(listRefs(repo.repoRoot)).resolves.toEqual([
                { name: 'feature', kind: 'branch', commit: repo.first },
                { name: 'trunk', kind: 'branch', commit: repo.second },
                { name: 'origin/trunk', kind: 'remote', commit: repo.first },
                { name: 'v10', kind: 'tag', commit: repo.second },
                { name: 'v2.0.0', kind: 'tag', commit: repo.second },
                { name: 'v2.0.0-rc1', kind: 'tag', commit: repo.second },
                { name: 'v1', kind: 'tag', commit: repo.first },
            ]);
        });

        it('sorts Shopware release tags like a release list', () => {
            const tags = [
                'backup/pre-rewrite',
                'v6.6.9.0',
                'v6.7.0.0-rc1',
                'v6.6.10.29',
                'v6.7.0.0',
                'v6.6.10.3',
                'v6.7.0.0-rc2',
            ];

            expect(tags.sort(compareVersionsDescending)).toEqual([
                'v6.7.0.0',
                'v6.7.0.0-rc2',
                'v6.7.0.0-rc1',
                'v6.6.10.29',
                'v6.6.10.3',
                'v6.6.9.0',
                'backup/pre-rewrite',
            ]);
        });
    });

    describe('resolveRef', () => {
        it.each([
            ['branch', 'feature', 'first'],
            ['current branch', 'trunk', 'second'],
            ['remote branch', 'origin/trunk', 'first'],
            ['annotated tag', 'v1', 'first'],
            ['lightweight tag', 'v2.0.0', 'second'],
        ] as const)('resolves a %s to its commit', async (_kind, ref, commit) => {
            await expect(resolveRef(repo.repoRoot, ref)).resolves.toBe(repo[commit]);
        });

        it('resolves full and abbreviated shas', async () => {
            await expect(resolveRef(repo.repoRoot, repo.first)).resolves.toBe(repo.first);
            await expect(resolveRef(repo.repoRoot, repo.first.slice(0, 10))).resolves.toBe(repo.first);
        });

        it('rejects unknown refs with a clear error', async () => {
            await expect(resolveRef(repo.repoRoot, 'does-not-exist')).rejects.toThrow(
                new GitRefError('Unknown git ref: does-not-exist'),
            );
        });

        it.each([
            '--output=/tmp/x',
            '-h',
            '',
            'trunk\nfeature',
        ])('rejects the ref %j before running git', async (ref) => {
            await expect(resolveRef(repo.repoRoot, ref)).rejects.toThrow(/^Invalid git ref/);
        });
    });

    describe('materializeRef', () => {
        it('extracts the admin sources of a commit with a node_modules symlink and removes them on dispose', async () => {
            const materialized = await materializeRef(repo.repoRoot, repo.first, TEST_ADMIN_PATH);
            const temporaryRoot = path.resolve(materialized.adminRoot, '..', '..');

            expect(materialized.adminRoot).toBe(path.join(temporaryRoot, 'packages', 'admin'));
            expect(fs.readFileSync(path.join(materialized.adminRoot, 'src/core/shopware.ts'), 'utf8')).toContain(
                'version: 1',
            );
            expect(fs.existsSync(path.join(materialized.adminRoot, 'tsconfig.json'))).toBe(true);
            expect(fs.existsSync(path.join(materialized.adminRoot, 'README.md'))).toBe(false);
            expect(fs.existsSync(path.join(materialized.adminRoot, 'src/core/helper.ts'))).toBe(true);
            expect(fs.existsSync(path.join(materialized.adminRoot, 'types/global.d.ts'))).toBe(true);
            expect(fs.existsSync(path.join(materialized.adminRoot, 'src/generated.d.ts'))).toBe(false);

            const nodeModules = path.join(materialized.adminRoot, 'node_modules');
            expect(fs.lstatSync(nodeModules).isSymbolicLink()).toBe(true);
            expect(fs.realpathSync(nodeModules)).toBe(path.join(repo.adminRoot, 'node_modules'));

            materialized.dispose();
            materialized.dispose();

            expect(fs.existsSync(temporaryRoot)).toBe(false);
            expect(fs.readFileSync(path.join(repo.adminRoot, 'node_modules/marker.txt'), 'utf8')).toBe(
                'real node_modules\n',
            );
        });

        it('copies extra files and keeps file modes and symlinks that stay inside the tree', async () => {
            const linkRepo = createTestRepo();

            try {
                linkRepo.write('src/run.sh', '#!/bin/sh\n');
                fs.chmodSync(path.join(linkRepo.adminRoot, 'src/run.sh'), 0o755);
                fs.symlinkSync('core/shopware.ts', path.join(linkRepo.adminRoot, 'src/inside.ts'));
                fs.symlinkSync('../../../../outside.txt', path.join(linkRepo.adminRoot, 'src/outside.txt'));
                fs.symlinkSync('/etc/hostname', path.join(linkRepo.adminRoot, 'src/absolute.txt'));
                linkRepo.git('add', '.');
                linkRepo.git('commit', '--quiet', '-m', 'links');

                const materialized = await materializeRef(
                    linkRepo.repoRoot,
                    linkRepo.git('rev-parse', 'HEAD'),
                    TEST_ADMIN_PATH,
                    { extraFiles: [{ path: `${TEST_ADMIN_PATH}/src/generated.d.ts`, content: Buffer.from('copied') }] },
                );

                try {
                    const src = path.join(materialized.adminRoot, 'src');

                    expect(fs.statSync(path.join(src, 'run.sh')).mode & 0o777).toBe(0o755);
                    expect(fs.readlinkSync(path.join(src, 'inside.ts'))).toBe('core/shopware.ts');
                    expect(fs.existsSync(path.join(src, 'outside.txt'))).toBe(false);
                    expect(fs.existsSync(path.join(src, 'absolute.txt'))).toBe(false);
                    expect(fs.readFileSync(path.join(src, 'generated.d.ts'), 'utf8')).toBe('copied');
                } finally {
                    materialized.dispose();
                }
            } finally {
                linkRepo.cleanup();
            }
        });

        it('refuses extra files outside the admin root', async () => {
            await expect(
                materializeRef(repo.repoRoot, repo.first, TEST_ADMIN_PATH, {
                    extraFiles: [{ path: 'outside.d.ts', content: Buffer.from('') }],
                }),
            ).rejects.toThrow('outside.d.ts is not below the admin root packages/admin');
        });

        it('fails without leaving files behind when the commit has no admin root', async () => {
            await expect(materializeRef(repo.repoRoot, repo.first, 'packages/missing')).rejects.toThrow(
                `Commit ${repo.first} has no packages/missing/src, packages/missing/tsconfig.json`,
            );
        });

        it('only accepts full commit shas', async () => {
            await expect(materializeRef(repo.repoRoot, 'trunk', TEST_ADMIN_PATH)).rejects.toThrow(
                'Expected a full commit sha',
            );
        });
    });

    it('lists git-ignored declarations below the admin sources', async () => {
        await expect(listIgnoredDeclarationFiles(repo.repoRoot, TEST_ADMIN_PATH)).resolves.toEqual([
            `${TEST_ADMIN_PATH}/src/generated.d.ts`,
        ]);
    });

    describe('getWorktreeStatus', () => {
        afterEach(() => {
            repo.git('checkout', '--quiet', '--', '.');
            fs.rmSync(path.join(repo.adminRoot, 'src/new-file.ts'), { force: true });
            fs.rmSync(path.join(repo.repoRoot, 'outside.txt'), { force: true });
        });

        it('reports branch and HEAD of a clean checkout', async () => {
            await expect(getWorktreeStatus(repo.repoRoot, TEST_ADMIN_PATH)).resolves.toEqual({
                branch: 'trunk',
                head: repo.second,
                changedFiles: 0,
            });
        });

        it('counts modified and untracked files below the admin root only', async () => {
            repo.write('src/core/shopware.ts', shopwareSource(3));
            repo.write('src/new-file.ts', 'export {};\n');
            fs.writeFileSync(path.join(repo.repoRoot, 'outside.txt'), 'outside\n');

            await expect(getWorktreeStatus(repo.repoRoot, TEST_ADMIN_PATH)).resolves.toMatchObject({ changedFiles: 2 });
            await expect(getWorktreeStatus(repo.repoRoot, '')).resolves.toMatchObject({ changedFiles: 3 });
        });
    });
});

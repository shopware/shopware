/**
 * @sw-package framework
 */

import { execFileSync } from 'node:child_process';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { isolatedGitEnv } from './run-git';

export const TEST_ADMIN_PATH = 'packages/admin';

/** File names in the snapshot cache of the test repository. */
export function cacheEntries(repo: TestRepo): string[] {
    const directory = path.join(repo.repoRoot, 'var', 'cache', 'api-explorer', 'snapshots');

    return fs.existsSync(directory) ? fs.readdirSync(directory).sort() : [];
}

export interface TestRepo {
    repoRoot: string;
    adminRoot: string;
    first: string;
    second: string;
    git(...args: string[]): string;
    write(adminRelativeFile: string, content: string): void;
    cleanup(): void;
}

const TSCONFIG = JSON.stringify({
    compilerOptions: {
        target: 'ES2022',
        module: 'commonjs',
        moduleResolution: 'node',
        strict: true,
        allowJs: true,
        skipLibCheck: true,
        types: [],
    },
    include: [
        './src/**/*',
        './types/**/*.d.ts',
    ],
});

export function shopwareSource(version: number): string {
    return [
        "import { createEntity } from './helper';",
        '',
        'export const ShopwareInstance = {',
        `    version: ${version},`,
        '    createEntity,',
        "    entity: createEntity('fixture'),",
        '    flag: FIXTURE_FLAG,',
        '};',
        '',
    ].join('\n');
}

/**
 * Throwaway repository below `os.tmpdir()`:
 * - first commit: admin fixture, tags `v1` (annotated) and branch `feature`; the exported types depend on a
 *   git-ignored declaration below src, an `export-ignore` source file and a declaration outside src
 * - second commit on `trunk`: changed source file, tags `v2.0.0-rc1`, `v2.0.0` and `v10`
 * - `origin/trunk` at the first commit, `origin/HEAD` pointing to it
 */
export function createTestRepo(): TestRepo {
    const repoRoot = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-repo-')));
    const adminRoot = path.join(repoRoot, ...TEST_ADMIN_PATH.split('/'));

    const git = (...args: string[]): string =>
        execFileSync('git', args, {
            cwd: repoRoot,
            env: isolatedGitEnv(),
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }).trim();
    const write = (adminRelativeFile: string, content: string): void => {
        const file = path.join(adminRoot, adminRelativeFile);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, content);
    };

    git('-c', 'init.defaultBranch=trunk', 'init', '--quiet');
    git('config', 'user.name', 'Api Explorer Test');
    git('config', 'user.email', 'api-explorer@example.com');
    git('config', 'commit.gpgsign', 'false');
    git('config', 'tag.gpgsign', 'false');

    fs.writeFileSync(path.join(repoRoot, '.gitignore'), `node_modules/\n${TEST_ADMIN_PATH}/src/generated.d.ts\n`);
    fs.writeFileSync(path.join(repoRoot, '.gitattributes'), `${TEST_ADMIN_PATH}/src/core/helper.ts export-ignore\n`);
    write('tsconfig.json', TSCONFIG);
    write('src/generated.d.ts', "declare interface GeneratedEntity { id: string; kind: 'generated' }\n");
    write(
        'src/core/helper.ts',
        'export function createEntity(id: string): GeneratedEntity {\n    return { id, kind: "generated" };\n}\n',
    );
    write('types/global.d.ts', "declare const FIXTURE_FLAG: 'on' | 'off';\n");
    write('src/core/shopware.ts', shopwareSource(1));
    write('README.md', 'not materialized\n');
    write('node_modules/marker.txt', 'real node_modules\n');

    git('add', '.');
    git('commit', '--quiet', '-m', 'first');
    const first = git('rev-parse', 'HEAD');
    git('tag', '-a', 'v1', '-m', 'v1');
    git('branch', 'feature');
    git('update-ref', 'refs/remotes/origin/trunk', first);
    git('symbolic-ref', 'refs/remotes/origin/HEAD', 'refs/remotes/origin/trunk');

    write('src/core/shopware.ts', shopwareSource(2));
    git('commit', '--quiet', '-am', 'second');
    const second = git('rev-parse', 'HEAD');
    git('tag', 'v2.0.0-rc1');
    git('tag', 'v2.0.0');
    git('tag', 'v10');

    return {
        repoRoot,
        adminRoot,
        first,
        second,
        git,
        write,
        cleanup: () => fs.rmSync(repoRoot, { recursive: true, force: true }),
    };
}

export interface PullRequestRemote {
    /** Bare repository standing in for GitHub. */
    url: string;
    /** Head of `refs/pull/7/head`, branched off `repo.second`. */
    head: string;
    /** Tip of `trunk` in the remote, one commit after `repo.second`. */
    baseTip: string;
    /** Moves `refs/pull/7/head` one commit ahead and returns the new head. */
    pushToPullRequest(): string;
    /** Merges the pull request into `trunk` and returns the merge or squash commit. */
    mergePullRequest(mode: 'merge' | 'squash'): string;
    /** Pushes `refs/pull/<number>/head` on a new branch off the remote `trunk` and returns its head. */
    openPullRequestOnTrunk(number: number): string;
    /** Shallow clone of the remote `trunk` with depth 1, `origin` pointing to github.com/acme/shop. */
    shallowClone(): { repoRoot: string; git(...args: string[]): string };
    cleanup(): void;
}

export function createPullRequestRemote(repo: TestRepo): PullRequestRemote {
    const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-remote-')));
    const url = path.join(root, 'remote.git');
    const work = path.join(root, 'work');
    const git = (cwd: string, ...args: string[]): string =>
        execFileSync('git', args, {
            cwd,
            env: isolatedGitEnv(),
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }).trim();
    const commit = (file: string, content: string, message: string): string => {
        const absolute = path.join(work, ...file.split('/'));
        fs.mkdirSync(path.dirname(absolute), { recursive: true });
        fs.writeFileSync(absolute, content);
        git(work, 'add', '.');
        git(work, '-c', 'user.name=Remote', '-c', 'user.email=remote@example.com', 'commit', '--quiet', '-m', message);

        return git(work, 'rev-parse', 'HEAD');
    };

    git(root, 'clone', '--quiet', '--bare', repo.repoRoot, url);
    git(url, 'config', 'uploadpack.allowAnySHA1InWant', 'true');
    git(root, 'clone', '--quiet', url, work);
    git(work, 'checkout', '--quiet', '-b', 'side', repo.second);
    const head = commit(`${TEST_ADMIN_PATH}/src/core/shopware.ts`, shopwareSource(3), 'pull request');
    git(work, 'push', '--quiet', 'origin', 'side:refs/pull/7/head');
    git(work, 'checkout', '--quiet', 'trunk');
    const baseTip = commit('CHANGELOG.md', 'trunk moved on\n', 'trunk moved on');
    git(work, 'push', '--quiet', 'origin', 'trunk');

    return {
        url,
        head,
        baseTip,
        pushToPullRequest() {
            git(work, 'checkout', '--quiet', 'side');
            const next = commit(`${TEST_ADMIN_PATH}/src/core/shopware.ts`, shopwareSource(4), 'review feedback');
            git(work, 'push', '--quiet', 'origin', 'side:refs/pull/7/head');

            return next;
        },
        mergePullRequest(mode) {
            git(work, 'checkout', '--quiet', 'trunk');
            const identity = [
                '-c',
                'user.name=Remote',
                '-c',
                'user.email=remote@example.com',
            ];

            if (mode === 'merge') {
                git(work, ...identity, 'merge', '--quiet', '--no-ff', '-m', 'merge pull request', 'side');
            } else {
                git(work, ...identity, 'merge', '--quiet', '--squash', 'side');
                git(work, ...identity, 'commit', '--quiet', '-m', 'squashed pull request');
            }

            git(work, 'push', '--quiet', 'origin', 'trunk');

            return git(work, 'rev-parse', 'HEAD');
        },
        openPullRequestOnTrunk(number) {
            git(work, 'checkout', '--quiet', '-B', `side-${number}`, 'origin/trunk');
            const next = commit(`${TEST_ADMIN_PATH}/src/core/later.ts`, `export const later = ${number};\n`, 'later');
            git(work, 'push', '--quiet', 'origin', `side-${number}:refs/pull/${number}/head`);

            return next;
        },
        shallowClone() {
            const clone = path.join(root, `shallow-${fs.readdirSync(root).length}`);
            git(root, 'clone', '--quiet', '--depth=1', '--branch', 'trunk', `file://${url}`, clone);
            git(clone, 'remote', 'set-url', 'origin', 'git@github.com:acme/shop.git');

            return { repoRoot: clone, git: (...args: string[]) => git(clone, ...args) };
        },
        cleanup: () => fs.rmSync(root, { recursive: true, force: true }),
    };
}

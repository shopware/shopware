/**
 * @sw-package framework
 *
 * Reads the Administration sources of a commit out of git, without a checkout. The break check compares two
 * commits with the generator of the checked-out one, so it needs the sources of both.
 */
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { isPublicApiSourceFile } from '../../public-api-source-files';
import { sourceTreeOf } from './source-tree';
import type { SourceTree } from './source-tree';

const MAX_OUTPUT = 1024 ** 3;

/**
 * @private
 *
 * The repository around an Administration folder. `administration` is the path of that folder in it.
 */
export interface GitRepository {
    root: string;
    administration: string;
}

function git(root: string, args: string[], input?: string): Buffer {
    return execFileSync('git', ['-C', root, ...args], { input, maxBuffer: MAX_OUTPUT, stdio: ['pipe', 'pipe', 'pipe'] });
}

function gitLine(root: string, args: string[]): string {
    return git(root, args).toString().trim();
}

/** @private */
export function repositoryOf(administrationRoot: string): GitRepository {
    const root = gitLine(administrationRoot, ['rev-parse', '--show-toplevel']);

    return { root, administration: path.relative(root, administrationRoot).split(path.sep).join('/') };
}

/**
 * @private
 *
 * The hash of the commit that `revision` names. A revision comes from the command line, and git would read
 * one that starts with a dash as an option. A hash is safe to pass on.
 */
export function resolveCommit(repository: GitRepository, revision: string): string {
    return gitLine(repository.root, [
        'rev-parse',
        '--verify',
        '--end-of-options',
        `${revision}^{commit}`,
    ]);
}

/**
 * `git cat-file --batch` answers each name with a header line, the content and a line break:
 * `<hash> blob <size>\n<content>\n`.
 */
function readBlobs(repository: GitRepository, names: string[]): string[] {
    const output = git(repository.root, ['cat-file', '--batch'], names.map((name) => `${name}\n`).join(''));
    let offset = 0;

    return names.map((name) => {
        const headerEnd = output.indexOf('\n', offset);
        const [, type, size] = output.subarray(offset, headerEnd).toString().split(' ');

        if (type !== 'blob') {
            throw new Error(`${name} is not a file in the repository.`);
        }

        const content = output.subarray(headerEnd + 1, headerEnd + 1 + Number(size)).toString();

        offset = headerEnd + 1 + Number(size) + 1;

        return content;
    });
}

/**
 * @private
 *
 * The files of the commit that can declare public API, as `readSourceTree` reads them from a checkout.
 */
export function readSourceTreeAt(repository: GitRepository, commit: string): SourceTree {
    const prefix = `${repository.administration}/`;
    const files = git(repository.root, [
        'ls-tree',
        '-r',
        '-z',
        '--name-only',
        commit,
        '--',
        `${prefix}src`,
    ])
        .toString()
        .split('\0')
        .filter((file) => file !== '')
        .map((file) => file.slice(prefix.length))
        .filter(isPublicApiSourceFile);
    const contents = new Map(
        readBlobs(
            repository,
            files.map((file) => `${commit}:${prefix}${file}`),
        ).map((content, index) => [files[index], content]),
    );

    return sourceTreeOf(files, (file) => contents.get(file) ?? '');
}

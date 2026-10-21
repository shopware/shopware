/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { runGit, runGitBuffer } from './run-git';

/**
 * Admin-root relative. Besides these, every tracked `.d.ts` file outside node_modules is materialized,
 * since the tsconfig can pull declarations from anywhere below the admin root.
 */
export const MATERIALIZED_FILES = [
    'tsconfig.json',
    'extension-tooling/tsconfig.base.json',
];

const SOURCE_DIRECTORY = 'src/';

export interface MaterializedFile {
    /** Repository relative with '/' separators; must be below the admin root. */
    path: string;
    content: Buffer;
}

export interface MaterializeOptions {
    /** Written on top of the tree, e.g. git-ignored generated declarations of the checkout. */
    extraFiles?: MaterializedFile[];
}

export interface MaterializedRef {
    /** Absolute admin root inside the temporary tree. */
    adminRoot: string;
    /** Removes the temporary tree; safe to call more than once. */
    dispose(): void;
}

interface TreeEntry {
    mode: string;
    type: string;
    object: string;
    /** Repository relative */
    path: string;
}

function assertSha(sha: string): void {
    if (!/^[0-9a-f]{40}$|^[0-9a-f]{64}$/.test(sha)) {
        throw new Error(`Expected a full commit sha, got ${JSON.stringify(sha)}`);
    }
}

export function normalizeAdminPath(adminRelativePath: string): string {
    const normalized = path.posix.normalize(adminRelativePath.split(path.sep).join('/')).replace(/\/+$/, '');

    if (normalized === '.' || normalized === '') {
        return '';
    }

    if (normalized.startsWith('..') || normalized.startsWith('/') || normalized.startsWith('-')) {
        throw new Error(`Invalid admin path: ${JSON.stringify(adminRelativePath)}`);
    }

    return normalized;
}

function toAdminRelative(adminPath: string, repoPath: string): string | null {
    if (adminPath === '') {
        return repoPath;
    }

    return repoPath.startsWith(`${adminPath}/`) ? repoPath.slice(adminPath.length + 1) : null;
}

function isMaterialized(adminRelative: string): boolean {
    return (
        adminRelative.startsWith(SOURCE_DIRECTORY) ||
        MATERIALIZED_FILES.includes(adminRelative) ||
        (adminRelative.endsWith('.d.ts') && !adminRelative.split('/').includes('node_modules'))
    );
}

function isInside(root: string, candidate: string): boolean {
    const relative = path.relative(root, candidate);

    return relative !== '' && !relative.startsWith('..') && !path.isAbsolute(relative);
}

function resolveInside(root: string, repoPath: string): string {
    const segments = repoPath.split('/');

    if (segments.some((segment) => segment === '' || segment === '.' || segment === '..')) {
        throw new Error(`Refusing to materialize the path ${JSON.stringify(repoPath)}`);
    }

    return path.join(root, ...segments);
}

async function listTree(repoRoot: string, sha: string, adminPath: string): Promise<TreeEntry[]> {
    const output = await runGit(repoRoot, [
        'ls-tree',
        '-r',
        '-z',
        '--full-tree',
        sha,
        '--',
        adminPath === '' ? '.' : adminPath,
    ]);

    return output
        .split('\0')
        .filter((line) => line !== '')
        .map((line) => {
            const tab = line.indexOf('\t');
            const [
                mode,
                type,
                object,
            ] = line.slice(0, tab).split(' ');

            return { mode, type, object, path: line.slice(tab + 1) };
        });
}

/** Contents in the order of `objects`. */
async function readBlobs(repoRoot: string, objects: string[]): Promise<Buffer[]> {
    if (objects.length === 0) {
        return [];
    }

    const output = await runGitBuffer(
        repoRoot,
        [
            'cat-file',
            '--batch',
        ],
        objects.map((object) => `${object}\n`).join(''),
    );
    const blobs: Buffer[] = [];
    let offset = 0;

    for (const object of objects) {
        const headerEnd = output.indexOf(0x0a, offset);
        const header = output.subarray(offset, headerEnd).toString('utf8').split(' ');

        if (header[0] !== object || header[1] !== 'blob') {
            throw new Error(`Cannot read object ${object}: ${header.join(' ')}`);
        }

        const start = headerEnd + 1;
        const end = start + Number(header[2]);
        blobs.push(output.subarray(start, end));
        offset = end + 1;
    }

    return blobs;
}

function writeFile(file: string, content: Buffer, mode: number): void {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content, { mode });
}

/**
 * Reads the files the analysis needs from `sha` into a temporary directory below `os.tmpdir()`, without
 * touching the checkout. Reads the object database directly, so `export-ignore` and other attributes do not
 * apply. `node_modules` is a symlink to the checkout's current one.
 */
export async function materializeRef(
    repoRoot: string,
    sha: string,
    adminRelativePath: string,
    { extraFiles = [] }: MaterializeOptions = {},
): Promise<MaterializedRef> {
    assertSha(sha);
    const adminPath = normalizeAdminPath(adminRelativePath);
    const entries = (await listTree(repoRoot, sha, adminPath)).filter((entry) => {
        const adminRelative = toAdminRelative(adminPath, entry.path);

        // Submodules (type 'commit') have no content in this repository.
        return adminRelative !== null && entry.type === 'blob' && isMaterialized(adminRelative);
    });
    const required = [
        SOURCE_DIRECTORY,
        'tsconfig.json',
    ].filter(
        (name) =>
            !entries.some((entry) => {
                const adminRelative = toAdminRelative(adminPath, entry.path) ?? '';

                return name.endsWith('/') ? adminRelative.startsWith(name) : adminRelative === name;
            }),
    );

    if (required.length > 0) {
        throw new Error(
            `Commit ${sha} has no ${required.map((name) => path.posix.join(adminPath, name).replace(/\/$/, '')).join(', ')}`,
        );
    }

    const extraFileTargets = extraFiles.map((file) => {
        if (toAdminRelative(adminPath, file.path) === null) {
            throw new Error(`${file.path} is not below the admin root ${adminPath}`);
        }

        return file.path;
    });

    const destination = fs.mkdtempSync(path.join(os.tmpdir(), `api-explorer-${sha.slice(0, 12)}-`));
    const dispose = (): void => fs.rmSync(destination, { recursive: true, force: true });

    try {
        const blobs = await readBlobs(
            repoRoot,
            entries.map((entry) => entry.object),
        );
        const symlinks: Array<[string, string]> = [];

        entries.forEach((entry, index) => {
            const file = resolveInside(destination, entry.path);

            if (entry.mode === '120000') {
                symlinks.push([
                    file,
                    blobs[index].toString('utf8'),
                ]);
            } else {
                writeFile(file, blobs[index], entry.mode === '100755' ? 0o755 : 0o644);
            }
        });

        // Links that leave the materialized tree would read from the checkout or elsewhere, so they are skipped.
        for (const [
            file,
            target,
        ] of symlinks) {
            if (!path.isAbsolute(target) && isInside(destination, path.resolve(path.dirname(file), target))) {
                fs.mkdirSync(path.dirname(file), { recursive: true });
                fs.symlinkSync(target, file);
            }
        }

        extraFiles.forEach((file, index) => {
            writeFile(resolveInside(destination, extraFileTargets[index]), file.content, 0o644);
        });

        const adminRoot = path.join(destination, ...adminPath.split('/').filter(Boolean));
        fs.symlinkSync(path.join(repoRoot, adminPath, 'node_modules'), path.join(adminRoot, 'node_modules'), 'dir');

        return { adminRoot, dispose };
    } catch (error) {
        dispose();
        throw error;
    }
}

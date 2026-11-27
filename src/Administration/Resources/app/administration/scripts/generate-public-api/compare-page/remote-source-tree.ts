/**
 * @sw-package framework
 *
 * Reads the Administration sources of commits from a remote instead of a checkout, so the break check can run in
 * a browser. `source/git-source-tree.ts` reads them from git.
 */
import { isPublicApiSourceFile } from '../../public-api-source-files';
import { sourceTreeOf } from '../source/source-tree';
import type { SourceTree } from '../source/source-tree';

/**
 * @private
 *
 * The files of a commit below the Administration folder, by their path below it. The value is a hash of the
 * content, so a file that two commits share is loaded once.
 */
export type FileList = ReadonlyMap<string, string>;

/**
 * @private
 *
 * Serves the Administration folder of a commit.
 */
export interface RemoteSource {
    listFiles(commit: string): Promise<FileList>;
    readFile(commit: string, file: string): Promise<string>;
}

/**
 * @private
 *
 * Keeps file contents by their hash between two checks.
 */
export interface ContentCache {
    get(hash: string): Promise<string | undefined>;
    set(hash: string, content: string): Promise<void>;
}

/** @private */
export interface LoadOptions {
    cache?: ContentCache;
    onProgress?: (loaded: number, total: number) => void;
}

interface Download {
    hash: string;
    commit: string;
    file: string;
}

/** How many files are requested at the same time. */
const CONCURRENCY = 48;

/** The files that can declare public API, as `readSourceTree` picks them from a checkout. */
function sourceFilesOf(list: FileList): FileList {
    return new Map([...list].filter(([file]) => file.startsWith('src/') && isPublicApiSourceFile(file)));
}

/** One download per content hash, from the first commit that has the file. */
function downloadsOf(commits: readonly string[], lists: readonly FileList[]): Download[] {
    const downloads = new Map<string, Download>();

    lists.forEach((list, index) => {
        list.forEach((hash, file) => {
            if (!downloads.has(hash)) {
                downloads.set(hash, { hash, commit: commits[index], file });
            }
        });
    });

    return [...downloads.values()];
}

async function loadContents(
    remote: RemoteSource,
    downloads: readonly Download[],
    { cache, onProgress }: LoadOptions,
): Promise<Map<string, string>> {
    const contents = new Map<string, string>();
    let next = 0;

    const loadOne = async ({ hash, commit, file }: Download): Promise<void> => {
        const cached = await cache?.get(hash);
        const content = cached ?? (await remote.readFile(commit, file));

        if (cached === undefined) {
            await cache?.set(hash, content);
        }

        contents.set(hash, content);
        onProgress?.(contents.size, downloads.length);
    };

    const worker = async (): Promise<void> => {
        while (next < downloads.length) {
            const download = downloads[next];

            next += 1;

            await loadOne(download);
        }
    };

    await Promise.all(Array.from({ length: Math.min(CONCURRENCY, downloads.length) }, worker));

    return contents;
}

/**
 * @private
 *
 * The source trees of `commits`, in their order.
 */
export async function loadSourceTrees(
    remote: RemoteSource,
    commits: readonly string[],
    options: LoadOptions = {},
): Promise<SourceTree[]> {
    const lists = (await Promise.all(commits.map((commit) => remote.listFiles(commit)))).map(sourceFilesOf);
    const contents = await loadContents(remote, downloadsOf(commits, lists), options);

    return lists.map((list) => sourceTreeOf([...list.keys()], (file) => contents.get(list.get(file) ?? '') ?? ''));
}

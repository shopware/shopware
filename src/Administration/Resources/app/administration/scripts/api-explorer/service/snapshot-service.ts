/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { createSnapshotStore, type SnapshotStore } from '../cache/snapshot-store';
import { getWorktreeStatus, listRefs, materializeRef, resolveRef, toAdminRelativePath, type GitRef } from '../git';
import type { WorktreeStatus } from '../git';
import { createAnalysisContext, type AnalysisContext } from '../snapshot/analysis-context';
import { createSnapshot, type SnapshotReuse } from '../snapshot/create-snapshot';
import { WORKTREE_REF, type ApiSnapshot } from '../snapshot/types';
import { collectEnvironment, type ExtractionEnvironment } from './environment';
import { extractRefInChildProcess, type ExtractRef } from './extract-ref';

export type { ExtractRef, ExtractRefRequest } from './extract-ref';

export interface SnapshotServiceOptions {
    repoRoot: string;
    /** The checkout's admin root; must be inside `repoRoot`. */
    adminRoot: string;
    /** Defaults to a child process running the snapshot command. */
    extractRef?: ExtractRef;
    /** Defaults to the store below `<repoRoot>/var/cache/api-explorer`. */
    store?: SnapshotStore;
}

export interface GetSnapshotOptions {
    /** `WORKTREE`: re-read the working tree. Other refs: ignore the cache and extract again. */
    refresh?: boolean;
}

export interface SnapshotServiceStatus extends WorktreeStatus {
    repoRoot: string;
    adminRoot: string;
    /** `createdAt` of the last working tree snapshot, null before the first one. */
    worktreeSnapshotCreatedAt: string | null;
}

export interface SnapshotService {
    /** `ref` is `WORKTREE_REF` or anything `git rev-parse` accepts; the returned snapshot carries `ref` as given. */
    getSnapshot(ref: string, options?: GetSnapshotOptions): Promise<ApiSnapshot>;
    listRefs(): Promise<GitRef[]>;
    status(): Promise<SnapshotServiceStatus>;
}

export function createSnapshotService({
    repoRoot: repoRootOption,
    adminRoot: adminRootOption,
    extractRef = extractRefInChildProcess,
    store: storeOption,
}: SnapshotServiceOptions): SnapshotService {
    const repoRoot = path.resolve(repoRootOption);
    const adminRoot = path.resolve(adminRootOption);
    const adminRelativePath = toAdminRelativePath(repoRoot, adminRoot);
    const store = storeOption ?? createSnapshotStore(repoRoot);

    let context: AnalysisContext | undefined;
    const worktreeReuse: SnapshotReuse = {};
    let worktreeSnapshot: ApiSnapshot | null = null;
    let worktreeTail: Promise<unknown> = Promise.resolve();
    let worktreeQueued: Promise<ApiSnapshot> | null = null;
    let worktreeLatest: Promise<ApiSnapshot> | null = null;
    const refExtractions = new Map<string, Promise<ApiSnapshot>>();

    async function extractWorktree(): Promise<ApiSnapshot> {
        context = createAnalysisContext(adminRoot, { previous: context });
        worktreeSnapshot = await createSnapshot({
            adminRoot,
            ref: WORKTREE_REF,
            commit: null,
            context,
            reuse: worktreeReuse,
        });

        return worktreeSnapshot;
    }

    // Refreshes run one after another; a refresh requested while another one waits joins the waiting one.
    function refreshWorktree(): Promise<ApiSnapshot> {
        if (worktreeQueued) {
            return worktreeQueued;
        }

        const run = worktreeTail
            .catch(() => undefined)
            .then(() => {
                worktreeQueued = null;

                return extractWorktree();
            });

        worktreeQueued = run;
        worktreeLatest = run;
        worktreeTail = run;

        return run;
    }

    function getWorktreeSnapshot(refresh: boolean): Promise<ApiSnapshot> {
        if (!refresh && worktreeLatest) {
            return worktreeLatest.catch(() => refreshWorktree());
        }

        return refreshWorktree();
    }

    async function extractCommit(ref: string, commit: string, environment: ExtractionEnvironment): Promise<ApiSnapshot> {
        const materialized = await materializeRef(repoRoot, commit, adminRelativePath, {
            extraFiles: environment.extraFiles,
        });

        try {
            const snapshot = await extractRef({ ref, commit, adminRoot: materialized.adminRoot });
            await store.write(commit, environment.key, snapshot);

            return snapshot;
        } finally {
            materialized.dispose();
        }
    }

    async function getRefSnapshot(ref: string, refresh: boolean): Promise<ApiSnapshot> {
        const commit = await resolveRef(repoRoot, ref);
        const environment = await collectEnvironment(repoRoot, adminRelativePath);
        const extractionKey = `${commit}-${environment.key}`;
        let pending = refExtractions.get(extractionKey);

        if (!pending) {
            const cached = refresh ? null : await store.read(commit, environment.key);

            if (cached) {
                return { ...cached, ref };
            }

            // Another caller may have started while the cache was read.
            pending = refExtractions.get(extractionKey);
        }

        if (!pending) {
            pending = extractCommit(ref, commit, environment).finally(() => refExtractions.delete(extractionKey));
            refExtractions.set(extractionKey, pending);
        }

        return { ...(await pending), ref };
    }

    return {
        getSnapshot(ref, { refresh = false } = {}) {
            return ref === WORKTREE_REF ? getWorktreeSnapshot(refresh) : getRefSnapshot(ref, refresh);
        },

        listRefs() {
            return listRefs(repoRoot);
        },

        async status() {
            return {
                repoRoot,
                adminRoot,
                ...(await getWorktreeStatus(repoRoot, adminRelativePath)),
                worktreeSnapshotCreatedAt: worktreeSnapshot?.createdAt ?? null,
            };
        },
    };
}

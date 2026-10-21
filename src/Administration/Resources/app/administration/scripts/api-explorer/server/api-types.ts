/**
 * @sw-package framework
 */

import type { ApiDiff } from '../diff/types';
import type { GitRef } from '../git';
import type { SnapshotServiceStatus } from '../service/snapshot-service';
import type { ApiSnapshot } from '../snapshot/types';

export interface StateResponse {
    status: SnapshotServiceStatus;
    refs: GitRef[];
    defaults: { base: string; head: string };
}

export interface CompareTimings {
    baseMs: number;
    headMs: number;
    diffMs: number;
}

export interface CompareResponse {
    base: ApiSnapshot;
    head: ApiSnapshot;
    diff: ApiDiff;
    timings: CompareTimings;
}

export interface PullRequestResponse {
    number: number;
    owner: string;
    repo: string;
    /** Null when the GitHub API was unavailable. */
    title: string | null;
    state: 'open' | 'closed' | null;
    merged: boolean | null;
    url: string;
    baseRef: string;
    headSha: string;
    mergeBaseSha: string;
    warnings: string[];
    /** Ref labels for `/api/compare`, e.g. `pr-base:19666` and `pr:19666`. */
    refs: { base: string; head: string };
}

export interface ErrorResponse {
    error: string;
}

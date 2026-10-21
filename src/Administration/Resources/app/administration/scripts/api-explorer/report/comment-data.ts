/**
 * @sw-package framework
 */

import { PUBLIC_SEVERITIES, type ApiChange, type ApiDiff, type PublicSeverity, type Severity } from '../diff/types';

export const COMMENT_DATA_VERSION = 2;
export const MAX_LISTED_CHANGES = 50;

const LIMITS = { ref: 100, kind: 40, owner: 200, member: 300, reason: 300 } as const;

export interface CommentDataChange {
    kind: string;
    owner: string;
    member: string;
    reason: string;
}

export interface CommentDataSide {
    ref: string;
    commit: string;
}

/** Plain data the trusted CI relay renders into the PR comment itself. */
export interface CommentData {
    version: typeof COMMENT_DATA_VERSION;
    base: CommentDataSide;
    head: CommentDataSide;
    counts: Record<Severity, number>;
    /** What the private changes would be if they were public; sums to `counts.private`. */
    privateCounts: Record<PublicSeverity, number>;
    breaking: CommentDataChange[];
    potentiallyBreaking: CommentDataChange[];
    omitted: { breaking: number; potentiallyBreaking: number };
}

/** One line without control characters, never empty, at most `limit` code points. */
function cut(text: string, limit: number): string {
    const clean = text
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\p{Cc}/gu, '');
    const codePoints = Array.from(clean || '-');

    return codePoints.length > limit ? `${codePoints.slice(0, limit - 1).join('')}…` : codePoints.join('');
}

function side(ref: string, commit: string | null): CommentDataSide {
    if (commit === null || !/^[0-9a-f]{40}$/.test(commit)) {
        throw new Error(`The commit of ${ref} is not a full sha: ${String(commit)}`);
    }

    return { ref: cut(ref, LIMITS.ref), commit };
}

function list(diff: ApiDiff, severity: Severity): { entries: CommentDataChange[]; omitted: number } {
    const changes = diff.changes.filter((change) => change.severity === severity);
    const toEntry = (change: ApiChange): CommentDataChange => ({
        kind: cut(change.kind, LIMITS.kind),
        owner: cut(change.owner, LIMITS.owner),
        member: cut(change.member, LIMITS.member),
        reason: cut(change.reasons.join(' '), LIMITS.reason),
    });

    return {
        entries: changes.slice(0, MAX_LISTED_CHANGES).map(toEntry),
        omitted: Math.max(0, changes.length - MAX_LISTED_CHANGES),
    };
}

function count(value: number): number {
    return Math.max(0, Math.trunc(value));
}

/** `headCommit` is the checkout's HEAD when the head is the working tree. */
export function createCommentData(diff: ApiDiff, headCommit: string | null): CommentData {
    const breaking = list(diff, 'breaking');
    const potentiallyBreaking = list(diff, 'potentiallyBreaking');
    const counts = Object.fromEntries(
        Object.entries(diff.summary.bySeverity).map(([severity, value]) => [severity, count(value)]),
    ) as Record<Severity, number>;
    const privateCounts = Object.fromEntries(
        PUBLIC_SEVERITIES.map((severity) => [severity, count(diff.summary.privateBySeverity[severity])]),
    ) as Record<PublicSeverity, number>;
    const privateTotal = PUBLIC_SEVERITIES.reduce((sum, severity) => sum + privateCounts[severity], 0);

    if (privateTotal !== counts.private) {
        throw new Error(`The private changes by severity sum to ${privateTotal}, not ${counts.private}.`);
    }

    return {
        version: COMMENT_DATA_VERSION,
        base: side(diff.base.ref, diff.base.commit),
        head: side(diff.head.ref, diff.head.commit ?? headCommit),
        counts,
        privateCounts,
        breaking: breaking.entries,
        potentiallyBreaking: potentiallyBreaking.entries,
        omitted: { breaking: breaking.omitted, potentiallyBreaking: potentiallyBreaking.omitted },
    };
}

/**
 * @sw-package framework
 */

import type { SnapshotDiagnostic, SourceLocation, Visibility } from '../snapshot/types';

/** Ordered from most to least severe; also the sort order of `ApiDiff.changes`. */
export const SEVERITIES = [
    'breaking',
    'potentiallyBreaking',
    'compatible',
    'added',
    'newlyDeprecated',
    'removedDeprecated',
    'private',
] as const;

export type Severity = (typeof SEVERITIES)[number];

export type PublicSeverity = Exclude<Severity, 'private'>;

/** `SEVERITIES` without 'private', in the same order. */
export const PUBLIC_SEVERITIES: readonly PublicSeverity[] = SEVERITIES.filter(
    (severity): severity is PublicSeverity => severity !== 'private',
);

export type NonPublicVisibility = Exclude<Visibility, 'public'>;

export type ChangeArea = 'shopwareObject' | 'component';

export type ChangeKind =
    | 'component'
    | 'prop'
    | 'emit'
    | 'slot'
    | 'data'
    | 'computed'
    | 'method'
    | 'inject'
    | 'mixin'
    | 'block'
    | 'objectMember';

export type ChangeType = 'added' | 'removed' | 'changed' | 'moved' | 'deprecated';

/** There is at most one change per `(area, owner, kind, member)`. */
export interface ApiChange {
    /** `${area}:${owner}:${kind}:${member}`, unique within a diff. */
    id: string;
    area: ChangeArea;
    kind: ChangeKind;
    change: ChangeType;
    severity: Severity;
    /** Component name; for `objectMember` the parent object path. */
    owner: string;
    /** Member name; the component name for `kind: 'component'`; the full object path for `objectMember`. */
    member: string;
    /** Human-readable signature or text in the base. */
    before: string | null;
    /** Human-readable signature or text in the head. */
    after: string | null;
    /** Short English sentences, e.g. 'Removed without prior deprecation.' */
    reasons: string[];
    /** Head location if present, else base. */
    location: SourceLocation | null;
    /** Set exactly when severity is 'private': the rating the change would get if the entry were public. */
    impact: PublicSeverity | null;
    /** Set exactly when severity is 'private': why the entry is not public. */
    privateVisibility: NonPublicVisibility | null;
}

export interface SnapshotRef {
    ref: string;
    commit: string | null;
    createdAt: string;
    extractorVersion: string;
    /** Surfaces partial extractions, which would otherwise look like mass removals. */
    diagnostics: SnapshotDiagnostic[];
}

export interface DiffSummary {
    total: number;
    bySeverity: Record<Severity, number>;
    /** Only breaking changes fail the CI check. */
    hasBreaking: boolean;
    /** Private changes counted by impact; sums to bySeverity.private. */
    privateBySeverity: Record<PublicSeverity, number>;
}

export interface ApiDiff {
    base: SnapshotRef;
    head: SnapshotRef;
    summary: DiffSummary;
    /** Sorted by severity (`SEVERITIES` order), then owner, then member. */
    changes: ApiChange[];
}

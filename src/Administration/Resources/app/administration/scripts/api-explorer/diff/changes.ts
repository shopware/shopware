/**
 * @sw-package framework
 */

import type { Deprecation, SourceLocation, Visibility } from '../snapshot/types';
import {
    SEVERITIES,
    type ApiChange,
    type ChangeArea,
    type ChangeKind,
    type ChangeType,
    type NonPublicVisibility,
    type PublicSeverity,
    type Severity,
} from './types';

/** One rule hit on an entry; several findings on the same entry are merged into one change. */
export interface Finding {
    severity: PublicSeverity;
    change: ChangeType;
    reason: string;
    /** Overrides the entry's rendered text, e.g. a block's parent chain for a move. */
    before?: string | null;
    after?: string | null;
}

export interface ChangeTarget {
    area: ChangeArea;
    kind: ChangeKind;
    owner: string;
    member: string;
    before: string | null;
    after: string | null;
    location: SourceLocation | null;
}

export interface VisibilityTransition {
    /** Effective visibility in the base. */
    base: Visibility;
    /** Effective visibility in the head. */
    head: Visibility;
    /** False when an enclosing entry already reports the same visibility change. */
    reportTransition: boolean;
}

export function severityRank(severity: Severity): number {
    return SEVERITIES.indexOf(severity);
}

function mostSevere(severities: PublicSeverity[]): PublicSeverity | null {
    return severities.reduce<PublicSeverity | null>(
        (best, severity) => (best === null || severityRank(severity) < severityRank(best) ? severity : best),
        null,
    );
}

export function isPublic(visibility: Visibility): boolean {
    return visibility === 'public';
}

export function normalizeText(text: string | null): string | null {
    return text === null ? null : text.replace(/\s+/g, ' ').trim();
}

export function textChanged(base: string | null, head: string | null): boolean {
    return normalizeText(base) !== normalizeText(head);
}

const MAX_QUOTED_LENGTH = 80;
const EXCERPT_CONTEXT = 15;

function excerpt(text: string, start: number, end: number): string {
    const from = Math.max(0, start - EXCERPT_CONTEXT);
    const to = Math.min(text.length, end + EXCERPT_CONTEXT);
    let part = `${from > 0 ? '…' : ''}${text.slice(from, to)}${to < text.length ? '…' : ''}`;

    if (part.length > MAX_QUOTED_LENGTH) {
        part = `${part.slice(0, MAX_QUOTED_LENGTH - 1)}…`;
    }

    return part;
}

/**
 * Long type text would bury the actual change, so it is only quoted when short; otherwise the reason
 * shows the differing part and `before`/`after` carry the full text.
 */
export function describeTextChange(subject: string, base: string | null, head: string | null): string {
    const before = normalizeText(base) ?? 'none';
    const after = normalizeText(head) ?? 'none';

    if (before.length <= MAX_QUOTED_LENGTH && after.length <= MAX_QUOTED_LENGTH) {
        return `${subject} changed from \`${before}\` to \`${after}\`.`;
    }

    const shorter = Math.min(before.length, after.length);
    let prefix = 0;
    while (prefix < shorter && before[prefix] === after[prefix]) {
        prefix += 1;
    }

    let suffix = 0;
    while (suffix < shorter - prefix && before[before.length - 1 - suffix] === after[after.length - 1 - suffix]) {
        suffix += 1;
    }

    const changedBefore = excerpt(before, prefix, before.length - suffix);
    const changedAfter = excerpt(after, prefix, after.length - suffix);

    return `${subject} changed. Changed part: \`${changedBefore}\` → \`${changedAfter}\`.`;
}

function withPeriod(text: string): string {
    return /[.!?]$/.test(text) ? text : `${text}.`;
}

function since(deprecation: Deprecation): string {
    return deprecation.version ? ` since ${deprecation.version}` : '';
}

export function sameDeprecation(a: Deprecation | null, b: Deprecation | null): boolean {
    if (a === null || b === null) {
        return a === b;
    }

    return a.version === b.version && normalizeText(a.message) === normalizeText(b.message);
}

export function deprecationFindings(base: Deprecation | null, head: Deprecation | null): Finding[] {
    if (base === null && head !== null) {
        const message = normalizeText(head.message);

        return [
            {
                severity: 'newlyDeprecated',
                change: 'deprecated',
                reason: withPeriod(`Newly deprecated${since(head)}${message ? `: ${message}` : ''}`),
            },
        ];
    }

    if (base !== null && head === null) {
        return [{ severity: 'compatible', change: 'changed', reason: 'Deprecation removed.' }];
    }

    return [];
}

/** Non-public changes keep severity 'private' and carry the rating they would get if the entry were public. */
interface PrivateRating {
    impact: PublicSeverity;
    visibility: NonPublicVisibility;
}

function createChange(
    target: ChangeTarget,
    severity: PublicSeverity,
    change: ChangeType,
    reasons: string[],
    privateRating: PrivateRating | null = null,
): ApiChange {
    return {
        id: `${target.area}:${target.owner}:${target.kind}:${target.member}`,
        area: target.area,
        kind: target.kind,
        change,
        severity: privateRating ? 'private' : severity,
        owner: target.owner,
        member: target.member,
        before: target.before,
        after: target.after,
        reasons,
        location: target.location,
        impact: privateRating?.impact ?? null,
        privateVisibility: privateRating?.visibility ?? null,
    };
}

function nonPublic(visibility: Visibility): NonPublicVisibility | null {
    return visibility === 'public' ? null : visibility;
}

/** A deprecated enclosing entry, e.g. the component of a member or the nearest deprecated object ancestor. */
export interface DeprecatedOwner {
    label: string;
    deprecation: Deprecation;
}

export function removedChange(
    target: ChangeTarget,
    visibility: Visibility,
    deprecation: Deprecation | null,
    deprecatedOwner: DeprecatedOwner | null = null,
): ApiChange {
    const removed = { ...target, after: null };
    const privateVisibility = nonPublic(visibility);
    let change: [PublicSeverity, string];

    if (deprecation !== null) {
        change = ['removedDeprecated', `Removed; it was deprecated${since(deprecation)}.`];
    } else if (deprecatedOwner !== null) {
        change = [
            'removedDeprecated',
            `Removed; ${deprecatedOwner.label} is deprecated${since(deprecatedOwner.deprecation)}.`,
        ];
    } else {
        change = ['breaking', 'Removed without prior deprecation.'];
    }

    if (privateVisibility !== null) {
        return createChange(removed, change[0], 'removed', [`Removed; it was @${privateVisibility}.`, change[1]], {
            impact: change[0],
            visibility: privateVisibility,
        });
    }

    return createChange(removed, change[0], 'removed', [change[1]]);
}

/**
 * `ownerVisibility` is the base visibility of the existing owner: an addition to an owner that was not public
 * cannot break anyone, even when the owner becomes public in the head.
 */
export function addedChange(
    target: ChangeTarget,
    visibility: Visibility,
    publicFinding?: Finding,
    ownerVisibility: Visibility = 'public',
): ApiChange {
    const added = { ...target, before: null };
    const impact = publicFinding?.severity ?? 'added';
    const privateVisibility = nonPublic(ownerVisibility) ?? nonPublic(visibility);

    if (!isPublic(ownerVisibility) && isPublic(visibility)) {
        return createChange(added, 'compatible', 'added', [`Added; its owner was @${ownerVisibility}.`]);
    }

    if (privateVisibility !== null) {
        const reasons = [`Added as @${visibility}.`, ...(publicFinding ? [publicFinding.reason] : [])];

        return createChange(added, impact, 'added', reasons, {
            impact,
            visibility: privateVisibility,
        });
    }

    return createChange(added, impact, 'added', [publicFinding?.reason ?? 'Added.']);
}

/** Folds the findings on a matched entry into one change, or null when nothing changed. */
export function resolveChange(
    target: ChangeTarget,
    findings: Finding[],
    visibility: VisibilityTransition,
): ApiChange | null {
    const all = [...findings];

    if (visibility.reportTransition && isPublic(visibility.base) && !isPublic(visibility.head)) {
        all.push({ severity: 'potentiallyBreaking', change: 'changed', reason: `Now marked @${visibility.head}.` });
    } else if (visibility.reportTransition && !isPublic(visibility.base) && isPublic(visibility.head)) {
        all.push({ severity: 'compatible', change: 'changed', reason: `Now public; it was @${visibility.base}.` });
    }

    if (all.length === 0) {
        return null;
    }

    const top = all.reduce((best, finding) =>
        severityRank(finding.severity) < severityRank(best.severity) ? finding : best,
    );
    const reasons = [...new Set(all.map((finding) => finding.reason))];
    const baseNonPublic = nonPublic(visibility.base);
    let severity = top.severity;
    let privateRating: PrivateRating | null = null;

    if (baseNonPublic !== null) {
        if (isPublic(visibility.head)) {
            severity = 'compatible';
        } else {
            privateRating = { impact: top.severity, visibility: baseNonPublic };
            reasons.unshift(`Not public; it is @${baseNonPublic}.`);
        }
    }

    return createChange(
        {
            ...target,
            before: top.before !== undefined ? top.before : target.before,
            after: top.after !== undefined ? top.after : target.after,
        },
        severity,
        top.change,
        reasons,
        privateRating,
    );
}

/** Keeps at most one change per id: the most severe one, with the reasons of all. */
export class ChangeCollector {
    private readonly changes = new Map<string, ApiChange>();

    add(change: ApiChange | null): void {
        if (change === null) {
            return;
        }

        const existing = this.changes.get(change.id);

        if (!existing) {
            this.changes.set(change.id, change);
            return;
        }

        const winner = severityRank(change.severity) < severityRank(existing.severity) ? change : existing;
        const impacts = [
            existing.impact,
            change.impact,
        ].filter((impact): impact is PublicSeverity => impact !== null);
        const impact = winner.severity === 'private' ? mostSevere(impacts) : null;
        this.changes.set(change.id, {
            ...winner,
            impact,
            reasons: [...new Set([...existing.reasons, ...change.reasons])],
        });
    }

    list(): ApiChange[] {
        return [...this.changes.values()];
    }
}

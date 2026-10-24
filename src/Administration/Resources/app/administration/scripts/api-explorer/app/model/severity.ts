/**
 * @sw-package framework
 */

import { SEVERITIES, type ApiChange, type ChangeKind, type Severity } from '../../diff/types';

export { SEVERITIES };
export type { Severity };

export interface SeverityMeta {
    label: string;
    symbol: string;
}

export const SEVERITY_META: Record<Severity, SeverityMeta> = {
    breaking: { label: 'Breaking', symbol: '×' },
    potentiallyBreaking: { label: 'Potentially breaking', symbol: '!' },
    compatible: { label: 'Compatible change', symbol: '~' },
    added: { label: 'Added', symbol: '+' },
    newlyDeprecated: { label: 'Newly deprecated', symbol: '↓' },
    removedDeprecated: { label: 'Removed (was deprecated)', symbol: '−' },
    private: { label: 'Private', symbol: '∘' },
};

export const KIND_LABELS: Record<ChangeKind, string> = {
    component: 'Component',
    prop: 'Prop',
    emit: 'Emit',
    slot: 'Slot',
    data: 'Data',
    computed: 'Computed',
    method: 'Method',
    inject: 'Inject',
    mixin: 'Mixin',
    block: 'Twig block',
    objectMember: 'Object member',
};

export function severityRank(severity: Severity): number {
    return SEVERITIES.indexOf(severity);
}

/** Most severe first. */
export function worstSeverity(changes: ApiChange[]): Severity | null {
    return changes.reduce<Severity | null>(
        (worst, change) => (worst === null || severityRank(change.severity) < severityRank(worst) ? change.severity : worst),
        null,
    );
}

export function countBySeverity(changes: ApiChange[]): Array<{ severity: Severity; count: number }> {
    return SEVERITIES.map((severity) => ({
        severity,
        count: changes.filter((change) => change.severity === severity).length,
    })).filter((entry) => entry.count > 0);
}

/** Mirrors `PublicSeverity` / `PUBLIC_SEVERITIES` of the diff contract. */
export type PublicSeverity = Exclude<Severity, 'private'>;

export const PUBLIC_SEVERITIES = SEVERITIES.filter((severity): severity is PublicSeverity => severity !== 'private');

export type PrivateVisibility = 'private' | 'internal' | 'experimental';

/** Set by the diff exactly when `severity` is 'private'; optional so snapshots of older diffs still render. */
export interface PrivateChangeFields {
    impact?: PublicSeverity | null;
    privateVisibility?: PrivateVisibility | null;
}

/** A severity tile ('breaking', 'private', …) or a private impact card ('private:breaking', …). */
export type ChangeFilter = Severity | `private:${PublicSeverity}`;

export interface PillInfo {
    /** Groups changes that render the same pill. */
    key: string;
    /** Drives symbol and colour: the impact for private changes that have one. */
    tone: Severity;
    label: string;
    symbol: string;
    /** 'Private', '@internal' or '@experimental' when the pill combines visibility and impact. */
    privatePrefix: string | null;
}

const PRIVATE_PREFIX: Record<PrivateVisibility, string> = {
    private: 'Private',
    internal: '@internal',
    experimental: '@experimental',
};

export function changeImpact(change: ApiChange): PublicSeverity | null {
    return change.severity === 'private' ? ((change as ApiChange & PrivateChangeFields).impact ?? null) : null;
}

export function pillInfo(change: Pick<ApiChange, 'severity'> & PrivateChangeFields): PillInfo {
    const impact = change.severity === 'private' ? (change.impact ?? null) : null;

    if (impact === null) {
        return {
            key: change.severity,
            tone: change.severity,
            label: SEVERITY_META[change.severity].label,
            symbol: SEVERITY_META[change.severity].symbol,
            privatePrefix: null,
        };
    }

    const visibility = change.privateVisibility ?? 'private';

    return {
        key: `private:${visibility}:${impact}`,
        tone: impact,
        label: SEVERITY_META[impact].label,
        symbol: SEVERITY_META[impact].symbol,
        privatePrefix: PRIVATE_PREFIX[visibility],
    };
}

export interface PillGroup extends PillInfo {
    count: number;
    /** The first change of the group, for rendering its pill. */
    change: ApiChange;
}

/** Pill groups in severity order, private groups by impact, each with its count. */
export function countByPill(changes: ApiChange[]): PillGroup[] {
    const groups = new Map<string, PillGroup>();
    const rank = (group: PillGroup): number => {
        const impact = changeImpact(group.change);

        return severityRank(group.change.severity) * 10 + (impact === null ? 0 : severityRank(impact) + 1);
    };

    changes.forEach((change) => {
        const info = pillInfo(change);
        const group = groups.get(info.key);

        if (group) {
            group.count += 1;
        } else {
            groups.set(info.key, { ...info, count: 1, change });
        }
    });

    return [...groups.values()].sort((a, b) => rank(a) - rank(b));
}

export function matchesFilter(change: ApiChange, filter: ChangeFilter | null): boolean {
    if (filter === null) {
        return true;
    }

    if (filter.startsWith('private:')) {
        return change.severity === 'private' && changeImpact(change) === filter.slice('private:'.length);
    }

    return change.severity === filter;
}

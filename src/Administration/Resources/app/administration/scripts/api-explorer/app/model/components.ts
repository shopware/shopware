/**
 * @sw-package framework
 */

import type { ApiChange, ApiDiff } from '../../diff/types';
import type { ApiSnapshot, ComponentEntry } from '../../snapshot/types';
import { severityRank, worstSeverity } from './severity';

export interface ComponentRow {
    name: string;
    base: ComponentEntry | null;
    head: ComponentEntry | null;
    changes: ApiChange[];
}

export function componentChanges(diff: ApiDiff): Map<string, ApiChange[]> {
    const byOwner = new Map<string, ApiChange[]>();

    diff.changes
        .filter((change) => change.area === 'component')
        .forEach((change) => {
            byOwner.set(change.owner, [
                ...(byOwner.get(change.owner) ?? []),
                change,
            ]);
        });

    return byOwner;
}

/** Changed components first, the most severe first, then by name. */
export function componentRows(base: ApiSnapshot, head: ApiSnapshot, diff: ApiDiff): ComponentRow[] {
    const changes = componentChanges(diff);
    const baseByName = new Map(
        base.components.map((component) => [
            component.name,
            component,
        ]),
    );
    const headByName = new Map(
        head.components.map((component) => [
            component.name,
            component,
        ]),
    );
    const names = new Set([
        ...headByName.keys(),
        ...baseByName.keys(),
    ]);
    const rank = (row: ComponentRow): number => {
        const worst = worstSeverity(row.changes);

        return worst === null ? Number.MAX_SAFE_INTEGER : severityRank(worst);
    };

    return [...names]
        .map((name) => ({
            name,
            base: baseByName.get(name) ?? null,
            head: headByName.get(name) ?? null,
            changes: changes.get(name) ?? [],
        }))
        .sort((a, b) => rank(a) - rank(b) || (a.name < b.name ? -1 : 1));
}

export function registrationLabel(component: ComponentEntry): string {
    return component.registration === 'extend' && component.extends !== null
        ? `extend ← ${component.extends}`
        : component.registration;
}

export function directoryOf(file: string): string {
    const index = file.lastIndexOf('/');

    return index === -1 ? file : file.slice(0, index);
}

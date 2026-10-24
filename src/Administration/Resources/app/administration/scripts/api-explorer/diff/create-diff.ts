/**
 * @sw-package framework
 */

import type { ApiSnapshot, SnapshotDiagnostic } from '../snapshot/types';
import { ChangeCollector, severityRank } from './changes';
import { diffComponents } from './components';
import { diffShopwareObject } from './shopware-object';
import {
    PUBLIC_SEVERITIES,
    SEVERITIES,
    type ApiChange,
    type PublicSeverity,
    type ApiDiff,
    type DiffSummary,
    type Severity,
    type SnapshotRef,
} from './types';

function compareText(a: string, b: string): number {
    if (a === b) {
        return 0;
    }

    return a < b ? -1 : 1;
}

function compareChanges(a: ApiChange, b: ApiChange): number {
    return (
        severityRank(a.severity) - severityRank(b.severity) ||
        compareText(a.owner, b.owner) ||
        compareText(a.member, b.member) ||
        compareText(a.id, b.id)
    );
}

function summarize(changes: ApiChange[]): DiffSummary {
    const bySeverity = Object.fromEntries(
        SEVERITIES.map((severity) => [
            severity,
            0,
        ]),
    ) as Record<Severity, number>;
    changes.forEach((change) => {
        bySeverity[change.severity] += 1;
    });

    const privateBySeverity = Object.fromEntries(
        PUBLIC_SEVERITIES.map((severity) => [
            severity,
            0,
        ]),
    ) as Record<PublicSeverity, number>;
    changes.forEach((change) => {
        if (change.impact !== null) {
            privateBySeverity[change.impact] += 1;
        }
    });

    return {
        total: changes.length,
        bySeverity,
        hasBreaking: bySeverity.breaking > 0,
        privateBySeverity,
    };
}

function snapshotRef(snapshot: ApiSnapshot, diagnostics: SnapshotDiagnostic[]): SnapshotRef {
    return {
        ref: snapshot.ref,
        commit: snapshot.commit,
        createdAt: snapshot.createdAt,
        extractorVersion: snapshot.extractorVersion,
        diagnostics: [
            ...snapshot.diagnostics,
            ...diagnostics,
        ],
    };
}

/**
 * Returns the side that came out empty while the other did not; an empty extraction must not read as
 * mass removals or additions.
 */
function emptySide(baseCount: number, headCount: number): 'base' | 'head' | null {
    if (baseCount === 0 && headCount > 0) {
        return 'base';
    }

    return headCount === 0 && baseCount > 0 ? 'head' : null;
}

export function createDiff(base: ApiSnapshot, head: ApiSnapshot): ApiDiff {
    const collector = new ChangeCollector();
    const diagnostics: Record<'base' | 'head', SnapshotDiagnostic[]> = { base: [], head: [] };

    const emptyComponents = emptySide(base.components.length, head.components.length);
    if (emptyComponents === null) {
        diffComponents(base.components, head.components, collector);
    } else {
        diagnostics[emptyComponents].push({
            file: null,
            message: 'No components were extracted, so components were not compared.',
        });
    }

    const emptyObject = emptySide(base.shopwareObject.children.length, head.shopwareObject.children.length);
    if (emptyObject === null) {
        diffShopwareObject(base.shopwareObject, head.shopwareObject, {
            collector,
            baseDiagnostics: diagnostics.base,
            headDiagnostics: diagnostics.head,
        });
    } else {
        diagnostics[emptyObject].push({
            file: null,
            message: 'The Shopware object has no members, so the Shopware object was not compared.',
        });
    }

    const changes = collector.list().sort(compareChanges);

    return {
        base: snapshotRef(base, diagnostics.base),
        head: snapshotRef(head, diagnostics.head),
        summary: summarize(changes),
        changes,
    };
}

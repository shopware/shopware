/**
 * @sw-package framework
 *
 * The findings about a unit as a whole: one that is gone, one that is new, and one that became private. The
 * rules in `rules/` compare the elements of a unit that both commits have.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import { FLAG_STATES } from '../model/flag-states';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { SourceLine } from '../model/source-line';
import { announcedBy } from './announcements';
import type { MarkerSources } from './announcements';
import { findingFor } from './finding';
import type { Finding, Judgement } from './finding';
import {
    ADDITION_FIX,
    additionText,
    announcesBecomingPrivate,
    becamePrivateTexts,
    inMajorState,
    removalFix,
} from './rules/texts';
import { UNIT_KINDS } from './units';
import type { CheckedUnit } from './units';

/** One unit at both commits. `base` or `head` is missing when the commit does not have the unit. */
interface Comparison {
    base: CheckedUnit | undefined;
    head: CheckedUnit | undefined;
    flags: FlagContext;
}

/**
 * A change to a unit as a whole. `at` is its line at the head commit, which a unit that is gone does not have.
 * `announcement` names the marker on the unit that allows the change.
 */
interface WholeChange {
    unit: CheckedUnit;
    states: FlagState[];
    judged: Judgement;
    at?: SourceLine;
    announcement?: { rule: string; markers: MarkerSources; matches: (marker: Marker) => boolean };
}

function statesWhere(matches: (state: FlagState) => boolean): FlagState[] {
    return FLAG_STATES.filter(matches);
}

function existsIn(unit: CheckedUnit | undefined, state: FlagState): boolean {
    return unit?.states[state] === true;
}

function markersOf(base: CheckedUnit, head: CheckedUnit | undefined): MarkerSources {
    return { base: () => base.markers, head: head?.markers ?? [] };
}

function reach({ kind, name }: CheckedUnit): string {
    return UNIT_KINDS[kind].use(name).unit;
}

function goneUnit({ base, head, flags }: Comparison): WholeChange[] {
    const states = statesWhere((state) => existsIn(base, state) && !existsIn(head, state));

    if (base === undefined || states.length === 0) {
        return [];
    }

    return [
        {
            unit: base,
            states,
            judged: {
                group: 'Break',
                rule: `${base.kind}_missing`,
                explanation: `The ${base.kind} is gone${inMajorState(states)}, so plugin code that uses ${reach(base)} gets undefined.`,
                fix: removalFix(base.kind, flags),
            },
            announcement: {
                rule: 'deprecated_removal',
                markers: markersOf(base, head),
                matches: (marker) => marker.tag === 'deprecated',
            },
        },
    ];
}

function newUnit({ base, head }: Comparison): WholeChange[] {
    const states = statesWhere((state) => existsIn(head, state) && !existsIn(base, state));

    if (head === undefined || states.length === 0) {
        return [];
    }

    return [
        {
            unit: head,
            states,
            judged: {
                group: 'Addition',
                rule: `${head.kind}_added`,
                explanation: additionText(head.kind),
                fix: ADDITION_FIX,
            },
            at: head.at,
        },
    ];
}

function unitBecamePrivate({ base, head, flags }: Comparison): WholeChange[] {
    const states = statesWhere((state) => existsIn(base, state) && existsIn(head, state));

    if (base?.visibility !== 'public' || head?.visibility !== 'private' || states.length === 0) {
        return [];
    }

    return [
        {
            unit: head,
            states,
            judged: {
                group: 'Break',
                rule: 'visibility_became_private',
                ...becamePrivateTexts(head.kind, reach(head), flags),
            },
            at: head.at,
            announcement: {
                rule: 'announced_change',
                markers: markersOf(base, head),
                matches: announcesBecomingPrivate,
            },
        },
    ];
}

/** What can happen to a unit as a whole. A new kind of change is one more function here. */
const WHOLE_CHANGES: readonly ((comparison: Comparison) => WholeChange[])[] = [
    goneUnit,
    newUnit,
    unitBecamePrivate,
];

/** A unit that was private at the base commit never was public API, and a new private one is none. */
function isPrivateChange({ base, head }: Comparison): boolean {
    return (base ?? head)?.visibility === 'private';
}

function findingOf({ unit, states, judged, at, announcement }: WholeChange, comparison: Comparison): Finding {
    const announced =
        announcement &&
        announcedBy(
            { rule: announcement.rule, subject: `The ${unit.kind}`, matches: announcement.matches },
            announcement.markers,
            states,
            comparison.flags,
        );

    return findingFor(announced ?? judged, { element: unit.id, states, isPrivate: isPrivateChange(comparison), at });
}

/**
 * @private
 *
 * The findings about the units that one commit has and the other has not, or has as private API only, for
 * the kinds that report a unit as a whole.
 */
export function unitFindings(
    base: ReadonlyMap<string, CheckedUnit>,
    head: ReadonlyMap<string, CheckedUnit>,
    flags: FlagContext,
): Finding[] {
    const ids = new Set([...base.keys(), ...head.keys()]);

    return [...ids].flatMap((id) => {
        const comparison: Comparison = { base: base.get(id), head: head.get(id), flags };
        const kind = (comparison.base ?? comparison.head)?.kind;
        const role = kind === undefined ? undefined : UNIT_KINDS[kind].role;

        return role?.is === 'api' && role.reportedAsWhole
            ? WHOLE_CHANGES.flatMap((change) => change(comparison)).map((change) => findingOf(change, comparison))
            : [];
    });
}

/**
 * @sw-package framework
 *
 * The findings about a unit as a whole: one that is gone, one that is new, and one that became private. The
 * rules in `rules/` compare the elements of a unit that both commits have.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import { FLAG_STATES } from '../model/flag-states';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { Member } from '../model/members';
import type { SourceLine } from '../model/source-line';
import { announcedBy } from './announcements';
import type { MarkerSources } from './announcements';
import { findingFor } from './finding';
import type { ExplainedFinding, Judgement } from './finding';
import type { PluginUse } from './plugin-use';
import {
    ADDITION_FIX,
    additionText,
    announcesBecomingPrivate,
    becamePrivateTexts,
    inMajorState,
    removalFix,
    removalTag,
    scriptComment,
} from './rules/texts';
import { UNIT_KINDS } from './units';
import type { CheckedUnit } from './units';

/**
 * One unit at both commits. `base` or `head` is missing when the commit does not have the unit. `heldAt` is
 * the line of the member that holds the unit at the head commit, for a unit that a member of another one holds.
 */
interface Comparison {
    base: CheckedUnit | undefined;
    head: CheckedUnit | undefined;
    flags: FlagContext;
    heldAt: SourceLine | undefined;
}

/**
 * A change to a unit as a whole. `at` is its line at the head commit. A unit that is gone has none, so it
 * points to the member that still holds it, which `isGone` says. `atBase` is its line at the base commit.
 * `announcement` names the marker on the unit that allows the change.
 */
interface WholeChange {
    unit: CheckedUnit;
    states: FlagState[];
    judged: Judgement;
    at?: SourceLine;
    atBase?: SourceLine;
    isGone?: true;
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

function useOf({ kind, name }: CheckedUnit): PluginUse {
    return UNIT_KINDS[kind].use(name);
}

function goneUnit({ base, head, flags, heldAt }: Comparison): WholeChange[] {
    const states = statesWhere((state) => existsIn(base, state) && !existsIn(head, state));

    if (base === undefined || states.length === 0) {
        return [];
    }

    const removalComment = scriptComment(removalTag(flags));

    return [
        {
            unit: base,
            states,
            judged: {
                group: 'Break',
                rule: `${base.kind}_missing`,
                headline: `The ${base.kind} ${base.name} is gone${inMajorState(states)}`,
                explanation: `The ${base.kind} is gone${inMajorState(states)}, so ${useOf(base).withoutUnit}.`,
                fix: removalFix(base.kind, removalComment),
                marker: removalComment,
                example: useOf(base).unitExample,
            },
            at: heldAt,
            atBase: base.at,
            isGone: true,
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
                ...becamePrivateTexts(head.kind, { name: head.name, used: useOf(head).unit }, flags),
                example: useOf(head).unitExample,
            },
            at: head.at,
            atBase: base.at,
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

type Units = ReadonlyMap<string, CheckedUnit>;

/** The member that holds the unit in a commit, in every flag state that has it, with the unit that has the member. */
function holderOf({ heldBy }: CheckedUnit, units: Units): { unit: CheckedUnit | undefined; members: Member[] } {
    if (heldBy === null) {
        return { unit: undefined, members: [] };
    }

    const { kind, name } = heldBy;
    const holder = units.get(heldBy.unit);

    return {
        unit: holder,
        members: FLAG_STATES.flatMap((state): Member[] => holder?.facts[state].members[kind].get(name) ?? []),
    };
}

/**
 * A unit that a member of another unit holds comes, goes and becomes private with that member or with that
 * unit, which reports it. A finding of its own is due when both commits have the member as public API of a
 * public unit: the snapshot can no longer read the object behind it, or it can now.
 */
function isLeftToHolder(unit: CheckedUnit, base: Units, head: Units): boolean {
    const isPublicIn = (units: Units): boolean => {
        const holder = holderOf(unit, units);

        return holder.unit?.visibility === 'public' && holder.members.some(({ isPrivate }) => !isPrivate);
    };

    return unit.heldBy !== null && !(isPublicIn(base) && isPublicIn(head));
}

/** A unit that was private at the base commit never was public API, and a new private one is none. */
function isPrivateChange({ base, head }: Comparison): boolean {
    return (base ?? head)?.visibility === 'private';
}

function findingOf(
    { unit, states, judged, at, atBase, isGone, announcement }: WholeChange,
    comparison: Comparison,
): ExplainedFinding {
    const announced =
        announcement &&
        announcedBy(
            { rule: announcement.rule, subject: `The ${unit.kind}`, matches: announcement.matches },
            announcement.markers,
            states,
            comparison.flags,
        );

    return findingFor(announced ?? judged, {
        element: unit.id,
        states,
        isPrivate: isPrivateChange(comparison),
        at,
        atBase,
        isGone,
    });
}

/**
 * @private
 *
 * The findings about the units that one commit has and the other has not, or has as private API only, for
 * the kinds that report a unit as a whole.
 */
export function unitFindings(base: Units, head: Units, flags: FlagContext): ExplainedFinding[] {
    return [...new Map([...base, ...head]).values()].flatMap((unit) => {
        const { role } = UNIT_KINDS[unit.kind];

        if (role.is !== 'api' || !role.reportedAsWhole || isLeftToHolder(unit, base, head)) {
            return [];
        }

        const comparison: Comparison = {
            base: base.get(unit.id),
            head: head.get(unit.id),
            flags,
            heldAt: holderOf(unit, head).members.at(0)?.at,
        };

        return WHOLE_CHANGES.flatMap((change) => change(comparison)).map((change) => findingOf(change, comparison));
    });
}

/**
 * @sw-package framework
 *
 * The break check: compares the models of the base and the head commit and reports what changed for plugins.
 * The rules find the changes per unit and flag state. This file decides what each change counts as: a break,
 * a change that a marker announced (see `announcements.ts`), or a change to a private unit. A unit that only
 * one of the commits has is the business of `unit-changes.ts`.
 */
import { byCodePoint } from '../helpers/code-point-order';
import { FLAG_STATES } from '../model/flag-states';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { Model } from '../model/model';
import { announced } from './announcements';
import type { ChangedFacts } from './facts/unit-facts';
import { FINDING_GROUPS, findingFor } from './finding';
import type { Finding } from './finding';
import { RULES } from './rules';
import type { Detection, Rule } from './rules/rule';
import { unitFindings } from './unit-changes';
import { checkedUnits, UNIT_KINDS } from './units';
import type { CheckedUnit } from './units';

/** A unit that both commits have, with its facts in each flag state. */
interface ComparedUnit extends ChangedFacts {
    base: CheckedUnit;
    head: CheckedUnit;
}

/** What a rule found for one element, with the flag states it found it in. */
interface Found {
    rule: Rule;
    detection: Detection;
    states: FlagState[];
}

/** A finding whose `element` is still the id below its unit. */
interface UnitFinding {
    unit: CheckedUnit;
    finding: Finding;
}

function unitsInBoth(base: ReadonlyMap<string, CheckedUnit>, head: ReadonlyMap<string, CheckedUnit>): ComparedUnit[] {
    return [...head.values()].flatMap((unit) => {
        const baseUnit = base.get(unit.id);

        return baseUnit === undefined
            ? []
            : [{ base: baseUnit, head: unit, baseFacts: baseUnit.facts, headFacts: unit.facts }];
    });
}

/** A unit that one commit has in a flag state and the other has not is no change to its elements. */
function foundBy(rule: Rule, { base, head, baseFacts, headFacts }: ComparedUnit): Found[] {
    const byElement = new Map<string, Found>();

    FLAG_STATES.filter((state) => base.states[state] && head.states[state]).forEach((state) => {
        rule.detect(baseFacts[state], headFacts[state]).forEach((detection) => {
            const key = `${detection.element}\n${detection.variant}`;
            const known = byElement.get(key);

            byElement.set(key, {
                rule,
                detection: known?.detection ?? detection,
                states: [
                    ...(known?.states ?? []),
                    state,
                ],
            });
        });
    });

    return [...byElement.values()];
}

/**
 * A unit that becomes private in the change was public API until then, so its changes still break plugins.
 * A private element of a public unit never was public API.
 */
function isPrivate({ base }: ComparedUnit, detection: Detection): boolean {
    return base.visibility === 'private' || detection.isPrivate === true;
}

function findingOf(result: Found, change: ComparedUnit, flags: FlagContext): Finding {
    const { rule, detection, states } = result;
    const { kind, name } = change.head;
    const judged = announced(detection.announcement, states, change, flags) ?? {
        group: rule.group,
        rule: rule.id,
        ...detection.texts({ unit: name, use: UNIT_KINDS[kind].use(name), states, flags }),
    };

    return findingFor(judged, {
        element: detection.element,
        states,
        isPrivate: isPrivate(change, detection),
        at: detection.at,
    });
}

function sameChange(a: Finding, b: Finding): boolean {
    return a.group === b.group && a.rule === b.rule && a.element === b.element && a.states.join() === b.states.join();
}

function reportedOn({ unit, finding }: UnitFinding, reached: UnitFinding[]): Finding {
    const alsoAffects = reached.map((other) => other.unit.name).sort(byCodePoint);

    return {
        ...finding,
        element: `${unit.id}/${finding.element}`,
        ...(alsoAffects.length === 0 ? {} : { alsoAffects }),
    };
}

/** The unit that reports a change its chain shares: the owner, and a grouping only when two units share it. */
function reporterOf(owner: UnitFinding, affected: UnitFinding[]): UnitFinding | undefined {
    return UNIT_KINDS[owner.unit.kind].role.is === 'api' || affected.length > 1 ? owner : undefined;
}

/**
 * A unit inherits the elements of its parent: an extend child those of the component it extends, and a service
 * those of its class. A change to the parent shows up in the child too. It is reported once, on the unit closest
 * to the root of the chain that has it, with the units it reaches. A class is no API of its own, so it only
 * reports a change that at least two services share, see `reporterOf`. A child keeps its own finding when its group differs from
 * the one of its parent, such as a public child of a private parent.
 */
function reportedOnce(findings: UnitFinding[]): Finding[] {
    const inherited = (reported: UnitFinding): UnitFinding | undefined =>
        findings.find(({ unit, finding }) => unit.id === reported.unit.parent && sameChange(finding, reported.finding));
    const ownerOf = (reported: UnitFinding): UnitFinding => {
        const fromParent = inherited(reported);

        return fromParent === undefined ? reported : ownerOf(fromParent);
    };
    const shared = new Map<UnitFinding, UnitFinding[]>();

    findings.forEach((reported) => {
        const owner = ownerOf(reported);

        shared.set(owner, [...(shared.get(owner) ?? []), reported]);
    });

    return [...shared].flatMap(([owner, reached]) => {
        const affected = reached.filter(({ unit }) => UNIT_KINDS[unit.kind].role.is === 'api');
        const reporter = reporterOf(owner, affected);

        return reporter === undefined
            ? affected.map((reported) => reportedOn(reported, []))
            : [
                  reportedOn(
                      reporter,
                      affected.filter((reported) => reported !== reporter),
                  ),
              ];
    });
}

function inSummaryOrder(a: Finding, b: Finding): number {
    return (
        FINDING_GROUPS.indexOf(a.group) - FINDING_GROUPS.indexOf(b.group) ||
        byCodePoint(a.rule, b.rule) ||
        byCodePoint(a.element, b.element) ||
        byCodePoint(a.states.join(), b.states.join())
    );
}

/**
 * @private
 *
 * Classifies the difference between the base and the head commit.
 */
export function classifyChanges(base: Model, head: Model): Finding[] {
    const before = checkedUnits(base);
    const after = checkedUnits(head);
    const findings = unitsInBoth(before, after).flatMap((change) =>
        RULES.flatMap((rule) => foundBy(rule, change)).map((result) => ({
            unit: change.head,
            finding: findingOf(result, change, head.flags),
        })),
    );

    return [...reportedOnce(findings), ...unitFindings(before, after, head.flags)].sort(inSummaryOrder);
}

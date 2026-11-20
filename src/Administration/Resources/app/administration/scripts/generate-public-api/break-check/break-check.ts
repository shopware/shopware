/**
 * @sw-package framework
 *
 * The break check: compares the models of the base and the head commit and reports what changed for plugins.
 * The rules find the changes per component and flag state. This file decides what each change counts as: a
 * break, a change that a marker announced (see `announcements.ts`), or a change to a private component.
 */
import { byCodePoint } from '../helpers/code-point-order';
import { FLAG_STATES } from '../model/flag-states';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { ComponentUnit, Model } from '../model/model';
import { announced } from './announcements';
import { changedFactsOf } from './facts/component-facts';
import type { ChangedFacts } from './facts/component-facts';
import { withInheritedMembers } from './facts/member-facts';
import { FINDING_GROUPS } from './finding';
import type { Finding } from './finding';
import { RULES } from './rules';
import type { Detection, Rule } from './rules/rule';

/** A component that both commits register, with its facts in each flag state. */
interface ComponentChange extends ChangedFacts {
    base: ComponentUnit;
    head: ComponentUnit;
}

/** What a rule found for one element, with the flag states it found it in. */
interface Found {
    rule: Rule;
    detection: Detection;
    states: FlagState[];
}

/** A finding whose `element` is still the id below its component. */
interface ComponentFinding {
    component: string;
    finding: Finding;
}

function byName({ components }: Model): Map<string, ComponentUnit> {
    return new Map(components.map((unit) => [unit.name, unit]));
}

/** An extend child is compared with the members it inherits, so an override it drops is no change to it. */
function componentChanges(base: Model, head: Model): ComponentChange[] {
    const before = byName(base);
    const after = byName(head);

    return head.components.flatMap((unit) => {
        const baseUnit = before.get(unit.name);

        if (baseUnit === undefined) {
            return [];
        }

        const was = withInheritedMembers(baseUnit, before);
        const now = withInheritedMembers(unit, after);

        return [{ base: was, head: now, ...changedFactsOf(was, now) }];
    });
}

/** A component that one commit registers in a flag state and the other does not is no change to its elements. */
function foundBy(rule: Rule, { base, head, baseFacts, headFacts }: ComponentChange): Found[] {
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
 * A component that becomes private in the change was public API until then, so its changes still break plugins.
 * A private element of a public component never was public API.
 */
function isPrivate({ base }: ComponentChange, detection: Detection): boolean {
    return base.visibility === 'private' || detection.isPrivate === true;
}

function findingOf(result: Found, change: ComponentChange, flags: FlagContext): Finding {
    const { rule, detection, states } = result;
    const judged = announced(detection.announcement, states, change, flags) ?? {
        group: rule.group,
        rule: rule.id,
        ...detection.texts({ component: change.head.name, states, flags }),
    };

    return {
        ...judged,
        group: isPrivate(change, detection) ? 'Private' : judged.group,
        element: detection.element,
        states,
        ...(detection.at === undefined ? {} : { at: `${detection.at.file}:${detection.at.line}` }),
    };
}

function sameChange(a: Finding, b: Finding): boolean {
    return a.group === b.group && a.rule === b.rule && a.element === b.element && a.states.join() === b.states.join();
}

/**
 * An extend child inherits the elements of its parent, so a change to the parent shows up in the child too.
 * It is reported once, on the component closest to the root of the chain that has it, with the children it
 * reaches. A public child of a private parent keeps its finding, because the two differ in their group.
 */
function reportedOnce(findings: ComponentFinding[], parentOf: (component: string) => string | null): Finding[] {
    const inherited = (reported: ComponentFinding): ComponentFinding | undefined => {
        const parent = parentOf(reported.component);

        return findings.find(({ component, finding }) => component === parent && sameChange(finding, reported.finding));
    };
    const ownerOf = (reported: ComponentFinding): ComponentFinding => {
        const fromParent = inherited(reported);

        return fromParent === undefined ? reported : ownerOf(fromParent);
    };
    const children = new Map<ComponentFinding, string[]>();

    findings.forEach((reported) => {
        const owner = ownerOf(reported);

        children.set(owner, [
            ...(children.get(owner) ?? []),
            ...(owner === reported ? [] : [reported.component]),
        ]);
    });

    return [...children].map(([{ component, finding }, alsoAffects]) => ({
        ...finding,
        element: `component:${component}/${finding.element}`,
        ...(alsoAffects.length === 0 ? {} : { alsoAffects: alsoAffects.sort(byCodePoint) }),
    }));
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
 * Classifies the difference between the base and the head commit. Only the components that both commits
 * register are compared.
 */
export function classifyChanges(base: Model, head: Model): Finding[] {
    const findings = componentChanges(base, head).flatMap((change) =>
        RULES.flatMap((rule) => foundBy(rule, change)).map((result) => ({
            component: change.head.name,
            finding: findingOf(result, change, head.flags),
        })),
    );
    const parents = byName(head);

    return reportedOnce(findings, (component) => parents.get(component)?.extends ?? null).sort(inSummaryOrder);
}

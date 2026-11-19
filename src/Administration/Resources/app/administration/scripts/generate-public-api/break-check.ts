/**
 * @sw-package framework
 *
 * The break check: compares the models of the base and the head commit and reports what changed for plugins.
 * The rules find the changes per component and flag state. This file decides what each change counts as: a
 * break, a change that a marker announced, or a change to a private component.
 */
import type { Marker } from '../../eslint-rules/deprecation-rules/utils/api-markers';
import { factsOf } from './block-facts';
import type { ComponentFacts } from './block-facts';
import { BLOCK_RULES } from './block-rules';
import { byCodePoint } from './code-point-order';
import { FINDING_GROUPS } from './findings';
import type { Finding } from './findings';
import { FLAG_STATES, isMajorOnly, perState } from './flag-states';
import type { FlagContext, FlagState } from './flag-states';
import type { ComponentUnit, Model } from './model';
import type { Detection, Rule } from './rules';

/** @private */
export interface BreakCheckOptions {
    /** The `major.minor` version the base branch releases next, for example `6.7`. */
    headVersion: string;
}

// The unit kinds built so far. `expected-failures.ts` lists what is missing.
const RULES: readonly Rule[] = BLOCK_RULES;

/** A component that both commits register, with its facts in each flag state. */
interface ComponentChange {
    base: ComponentUnit;
    head: ComponentUnit;
    baseFacts: Record<FlagState, ComponentFacts>;
    headFacts: Record<FlagState, ComponentFacts>;
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

interface CheckContext {
    flags: FlagContext;
    headVersion: string;
}

function componentChanges(base: Model, head: Model): ComponentChange[] {
    const before = new Map(base.components.map((unit) => [unit.name, unit]));

    return head.components.flatMap((unit) => {
        const baseUnit = before.get(unit.name);

        return baseUnit === undefined
            ? []
            : [
                  {
                      base: baseUnit,
                      head: unit,
                      baseFacts: perState((state) => factsOf(baseUnit, state)),
                      headFacts: perState((state) => factsOf(unit, state)),
                  },
              ];
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
 * A marker allows its change from the version it names on. A change that only the major state has is allowed
 * by a marker for that major, which is how a change is prepared behind the major flag.
 */
function isDue({ version }: Marker, states: FlagState[], { flags, headVersion }: CheckContext): boolean {
    const [major, minor] = version.slice(1).split('.').map(Number);
    const [headMajor, headMinor] = headVersion.split('.').map(Number);
    const released = major < headMajor || (major === headMajor && minor <= headMinor);

    return released || (isMajorOnly(states) && version === flags.majorVersion);
}

function announcedText(subject: string, marker: Marker): string {
    return marker.tag === 'deprecated'
        ? `${subject} carries @deprecated tag:${marker.version}, which announced that it goes away.`
        : `${subject} carries @bc-change ${marker.kind} version:${marker.version}, which announced this change.`;
}

/**
 * What the finding says when a marker announced the change. For a change in the major state only the marker
 * may come with the same pull request, so the markers of the current state at the head count too.
 */
function announced(
    { detection: { announcement }, states }: Found,
    change: ComponentChange,
    context: CheckContext,
): Pick<Finding, 'group' | 'rule' | 'explanation'> | undefined {
    if (announcement === undefined) {
        return undefined;
    }

    const marker = [
        ...states.flatMap((state) => announcement.markers(change.baseFacts[state])),
        ...announcement.markers(change.headFacts.current),
    ].find((candidate) => announcement.matches(candidate) && isDue(candidate, states, context));

    return marker && { group: 'Allowed', rule: announcement.rule, explanation: announcedText(announcement.subject, marker) };
}

function isPrivate({ base, head }: ComponentChange): boolean {
    return base.visibility === 'private' || head.visibility === 'private';
}

function findingOf(result: Found, change: ComponentChange, context: CheckContext): Finding {
    const { rule, detection, states } = result;
    const judged = announced(result, change, context) ?? {
        group: rule.group,
        rule: rule.id,
        ...detection.texts({ component: change.head.name, states, flags: context.flags }),
    };

    return {
        ...judged,
        group: isPrivate(change) ? 'Private' : judged.group,
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
export function classifyChanges(base: Model, head: Model, { headVersion }: BreakCheckOptions): Finding[] {
    const context = { flags: head.flags, headVersion };
    const findings = componentChanges(base, head).flatMap((change) =>
        RULES.flatMap((rule) => foundBy(rule, change)).map((result) => ({
            component: change.head.name,
            finding: findingOf(result, change, context),
        })),
    );
    const parents = new Map(head.components.map((unit) => [unit.name, unit.extends]));

    return reportedOnce(findings, (component) => parents.get(component) ?? null).sort(inSummaryOrder);
}

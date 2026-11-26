/**
 * @sw-package framework
 *
 * A finding of the break check: the groups it can be in and the flag states it is about. `print-findings.ts`
 * prints the findings.
 */
import type { FlagState } from '../model/flag-states';
import type { SourceLine } from '../model/source-line';

/**
 * @private
 *
 * In this order the summary prints the groups. `Allowed` is a change that a marker announced, and `Private` a
 * change to a private element, which is never public API.
 */
export const FINDING_GROUPS = [
    'Break',
    'Addition',
    'Allowed',
    'Private',
    'Info',
] as const;

/** @private */
export type FindingGroup = (typeof FINDING_GROUPS)[number];

/**
 * @private
 *
 * One change the break check reports. `element` is the element id, for example
 * `component:sw-card/block:sw_card_header`. `alsoAffects` names the extend children that inherit the change, or
 * the services that share the change of their class.
 */
export interface Finding {
    group: FindingGroup;
    rule: string;
    element: string;
    states: FlagState[];
    explanation: string;
    fix?: string;
    possibleRename?: string;
    alsoAffects?: string[];
    at?: string;
}

/** @private */
export function isMajorOnly(states: readonly FlagState[]): boolean {
    return states.length === 1 && states[0] === 'major';
}

/**
 * @private
 *
 * What a rule or an announcement says about a change, before the break check knows where it is and whether it
 * is about private API.
 */
export type Judgement = Pick<Finding, 'group' | 'rule' | 'explanation' | 'fix' | 'possibleRename'>;

/**
 * @private
 *
 * The finding for a judged change. A change to private API is listed as `Private`, whatever it would count as.
 */
export function findingFor(
    judged: Judgement,
    { element, states, isPrivate, at }: { element: string; states: FlagState[]; isPrivate: boolean; at?: SourceLine },
): Finding {
    return {
        ...judged,
        group: isPrivate ? 'Private' : judged.group,
        element,
        states,
        ...(at === undefined ? {} : { at: `${at.file}:${at.line}` }),
    };
}

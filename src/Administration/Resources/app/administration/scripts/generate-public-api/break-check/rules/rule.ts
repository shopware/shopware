/**
 * @sw-package framework
 *
 * What a rule of the break check is made of. A rule compares one component in one flag state at the base and
 * at the head commit. It knows nothing about flag states, markers or visibility, which `break-check.ts` and `announcements.ts` add.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagState } from '../../model/flag-states';
import type { SourceLine } from '../../model/source-line';
import type { ComponentFacts } from '../facts/component-facts';
import type { Finding, FindingGroup } from '../finding';

/**
 * @private
 *
 * What the text of a finding can refer to besides the change itself.
 */
export interface TextContext {
    component: string;
    states: FlagState[];
    flags: FlagContext;
}

/** @private */
export type FindingTexts = Pick<Finding, 'explanation' | 'fix' | 'possibleRename'>;

/**
 * @private
 *
 * How a marker can announce a change: `markers` reads the markers of the changed element out of the facts of
 * a commit, and `matches` says whether one of them names this change. `rule` is the rule the finding gets
 * then, and `subject` what its text says carries the marker, for example `The block`.
 */
export interface Announcement {
    rule: string;
    subject: string;
    markers: (facts: ComponentFacts) => Marker[];
    matches: (marker: Marker) => boolean;
}

/**
 * @private
 *
 * One change a rule found in one flag state. `element` is the element id below the component, for example
 * `block:sw_card_title/scope:item`. Two flag states with the same `element` and `variant` are one finding,
 * so `variant` holds everything the texts are made of. `isPrivate` says that the element itself is `@private`
 * inside a public component.
 */
export interface Detection {
    element: string;
    at?: SourceLine;
    isPrivate?: boolean;
    variant: string;
    texts: (context: TextContext) => FindingTexts;
    announcement?: Announcement;
}

/** @private */
export interface Rule {
    id: string;
    group: FindingGroup;
    detect: (base: ComponentFacts, head: ComponentFacts) => Detection[];
}

/** The element a change is about. */
type Element = Pick<Detection, 'element' | 'at' | 'isPrivate'>;

/**
 * @private
 *
 * A rule as it is written down: `changes` finds the changes with the element each one is about, and `texts`
 * words one of them. `announcement` names the marker that allows the change.
 */
export interface RuleDefinition<Change> {
    id: string;
    group: FindingGroup;
    changes: (base: ComponentFacts, head: ComponentFacts) => (Change & Element)[];
    texts: (change: Change, context: TextContext) => FindingTexts;
    announcement?: Pick<Announcement, 'rule' | 'subject'> & {
        markers: (change: Change, facts: ComponentFacts) => Marker[];
        matches: (change: Change, marker: Marker) => boolean;
    };
}

/** @private */
export function rule<Change>({ id, group, changes, texts, announcement }: RuleDefinition<Change>): Rule {
    return {
        id,
        group,
        detect: (base, head) =>
            changes(base, head).map((change) => {
                const { element, at, isPrivate, ...detail } = change;

                return {
                    element,
                    at,
                    isPrivate,
                    variant: JSON.stringify(detail),
                    texts: (context) => texts(change, context),
                    announcement: announcement && {
                        ...announcement,
                        markers: (facts) => announcement.markers(change, facts),
                        matches: (marker) => announcement.matches(change, marker),
                    },
                };
            }),
    };
}

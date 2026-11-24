/**
 * @sw-package framework
 *
 * What a rule of the break check is made of. A rule compares one unit in one flag state at the base and at the
 * head commit. It knows nothing about flag states, markers or visibility, which `break-check.ts` and
 * `announcements.ts` add.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagState } from '../../model/flag-states';
import type { SourceLine } from '../../model/source-line';
import type { UnitFacts } from '../facts/unit-facts';
import type { Finding, FindingGroup } from '../finding';
import type { PluginUse } from '../plugin-use';

/**
 * @private
 *
 * What the text of a finding can refer to besides the change itself: the name of the unit, and how plugin code
 * uses its members.
 */
export interface TextContext {
    unit: string;
    use: PluginUse;
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
    markers: (facts: UnitFacts) => Marker[];
    matches: (marker: Marker) => boolean;
}

/**
 * @private
 *
 * One change a rule found in one flag state. `element` is the element id below the unit, for example
 * `block:sw_card_title/scope:item`. Two flag states with the same `element` and `variant` are one finding,
 * so `variant` holds everything the texts are made of. `isPrivate` says that the element itself is `@private`
 * inside a public unit.
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
    detect: (base: UnitFacts, head: UnitFacts) => Detection[];
}

/**
 * @private
 *
 * A word or phrase of a finding that may depend on the change, as `The method` or `The action` does.
 */
export type Worded<Change> = string | ((change: Change) => string);

/** @private */
export function worded<Change>(phrase: Worded<Change>, change: Change): string {
    return typeof phrase === 'string' ? phrase : phrase(change);
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
    changes: (base: UnitFacts, head: UnitFacts) => (Change & Element)[];
    texts: (change: Change, context: TextContext) => FindingTexts;
    announcement?: Pick<Announcement, 'rule'> & {
        /** What the text says carries the marker. */
        subject: Worded<Change>;
        markers: (change: Change, facts: UnitFacts) => Marker[];
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
                        rule: announcement.rule,
                        subject: worded(announcement.subject, change),
                        markers: (facts) => announcement.markers(change, facts),
                        matches: (marker) => announcement.matches(change, marker),
                    },
                };
            }),
    };
}

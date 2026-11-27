/**
 * @sw-package framework
 *
 * Decides whether a marker announced a change that a rule found. An announced change is allowed and no break.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { ChangedFacts } from './facts/unit-facts';
import { isMajorOnly } from './finding';
import type { Finding } from './finding';
import type { Announcement } from './rules/rule';

/** A marker counts once a minor before the next major has released the version it names. */
function isReleased({ version }: Marker, flags: FlagContext): boolean {
    const [major, minor] = version.slice(1).split('.').map(Number);
    const [nextMajor, nextMinor] = flags.majorVersion.slice(1).split('.').map(Number);

    return major < nextMajor || (major === nextMajor && minor < nextMinor);
}

/**
 * @private
 *
 * The markers of the changed element: at the base commit in a flag state, and at the head commit.
 */
export interface MarkerSources {
    base: (state: FlagState) => Marker[];
    head: Marker[];
}

/**
 * The markers that can announce a change. A change that only the major state has is also announced by a marker
 * for that major, which is how a change is prepared behind the major flag. Only such a marker may come with the
 * same pull request, so it is the one marker of the head that counts.
 */
function dueMarkers(markers: MarkerSources, states: FlagState[], flags: FlagContext): Marker[] {
    const isForMajor = ({ version }: Marker): boolean => isMajorOnly(states) && version === flags.majorVersion;
    const fromBase = states.flatMap(markers.base).filter((marker) => isReleased(marker, flags) || isForMajor(marker));
    const fromHead = markers.head.filter(isForMajor);

    return [
        ...fromBase,
        ...fromHead,
    ];
}

function announcedText(subject: string, marker: Marker): string {
    return marker.tag === 'deprecated'
        ? `${subject} carries @deprecated tag:${marker.version}, which announced that it goes away.`
        : `${subject} carries @bc-change ${marker.kind} version:${marker.version}, which announced this change.`;
}

type AnnouncedFinding = Pick<Finding, 'group' | 'rule' | 'explanation'>;

/**
 * @private
 *
 * What the finding says when one of `markers` announced the change, `undefined` when none did.
 */
export function announcedBy(
    announcement: Pick<Announcement, 'rule' | 'subject' | 'matches'>,
    markers: MarkerSources,
    states: FlagState[],
    flags: FlagContext,
): AnnouncedFinding | undefined {
    const marker = dueMarkers(markers, states, flags).find((candidate) => announcement.matches(candidate));

    return marker && { group: 'Allowed', rule: announcement.rule, explanation: announcedText(announcement.subject, marker) };
}

/**
 * @private
 *
 * The same for a change a rule found, whose announcement reads the markers out of the facts of the unit.
 */
export function announced(
    announcement: Announcement | undefined,
    states: FlagState[],
    change: ChangedFacts,
    flags: FlagContext,
): AnnouncedFinding | undefined {
    return (
        announcement &&
        announcedBy(
            announcement,
            {
                base: (state) => announcement.markers(change.baseFacts[state]),
                head: announcement.markers(change.headFacts.current),
            },
            states,
            flags,
        )
    );
}

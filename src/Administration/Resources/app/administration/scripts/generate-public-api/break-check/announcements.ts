/**
 * @sw-package framework
 *
 * Decides whether a marker announced a change that a rule found. An announced change is allowed and no break.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagState } from '../model/flag-states';
import type { ChangedFacts } from './facts/component-facts';
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
 * The markers that can announce a change. A change that only the major state has is also announced by a marker
 * for that major, which is how a change is prepared behind the major flag. Only such a marker may come with the
 * same pull request, so it is the one marker of the head that counts.
 */
function dueMarkers(announcement: Announcement, states: FlagState[], change: ChangedFacts, flags: FlagContext): Marker[] {
    const isForMajor = ({ version }: Marker): boolean => isMajorOnly(states) && version === flags.majorVersion;
    const fromBase = states
        .flatMap((state) => announcement.markers(change.baseFacts[state]))
        .filter((marker) => isReleased(marker, flags) || isForMajor(marker));
    const fromHead = announcement.markers(change.headFacts.current).filter(isForMajor);

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

/**
 * @private
 *
 * What the finding says when a marker announced the change, `undefined` when none did.
 */
export function announced(
    announcement: Announcement | undefined,
    states: FlagState[],
    change: ChangedFacts,
    flags: FlagContext,
): Pick<Finding, 'group' | 'rule' | 'explanation'> | undefined {
    if (announcement === undefined) {
        return undefined;
    }

    const marker = dueMarkers(announcement, states, change, flags).find((candidate) => announcement.matches(candidate));

    return marker && { group: 'Allowed', rule: announcement.rule, explanation: announcedText(announcement.subject, marker) };
}

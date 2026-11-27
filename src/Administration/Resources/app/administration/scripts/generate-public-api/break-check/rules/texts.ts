/**
 * @sw-package framework
 *
 * The texts and marker checks that a finding about a member and a finding about a whole unit share, so a
 * removal, an addition and a change of visibility read the same wherever they happen.
 */
import type { BcChangeKind, Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagState } from '../../model/flag-states';
import { isMajorOnly } from '../finding';
import type { BreakTexts } from './rule';

/**
 * @private
 *
 * A `@bc-change` of the kind whose keys hold the `expected` values.
 */
export function isBcChange(marker: Marker, kind: BcChangeKind, expected: Record<string, string> = {}): boolean {
    return (
        marker.tag === 'bc-change' &&
        marker.kind === kind &&
        Object.entries(expected).every(([key, value]) => marker.parameters[key] === value)
    );
}

/**
 * @private
 *
 * What follows `is gone` when the change is behind the major flag.
 */
export function inMajorState(states: readonly FlagState[]): string {
    return isMajorOnly(states) ? ' in the major state' : '';
}

function deprecationTag(flags: FlagContext, text: string): string {
    return `@deprecated tag:${flags.majorVersion} - ${text}`;
}

/** @private */
export function removalTag(flags: FlagContext): string {
    return deprecationTag(flags, 'Will be removed.');
}

/** @private */
export function renameTag(rename: string, flags: FlagContext): string {
    return deprecationTag(flags, `Use {@link ${rename}} instead.`);
}

/**
 * @private
 *
 * A `@bc-change` of the next major. `keys` are its parameters, `parameter:id`.
 */
export function bcChangeTag(kind: BcChangeKind, flags: FlagContext, keys = ''): string {
    return [`@bc-change ${kind} version:${flags.majorVersion}`, keys].filter(Boolean).join(' ');
}

/**
 * @private
 *
 * The tag as a script writes it, in a comment above the member.
 */
export function scriptComment(tag: string): string {
    return `/** ${tag} */`;
}

/**
 * @private
 *
 * The tag as a template writes it, in a comment before the block.
 */
export function twigComment(tag: string): string {
    return `{# ${tag} #}`;
}

/**
 * @private
 *
 * `marker` is the comment that announces the removal.
 */
export function removalFix(label: string, marker: string): string {
    return `pattern "Removal". Keep the ${label} and mark it ${marker}.`;
}

/** @private */
export const ADDITION_FIX = 'mark it @private if it is not meant for plugins.';

/** @private */
export function additionText(label: string): string {
    return `A new public ${label} that plugins can rely on from now on.`;
}

/**
 * @private
 *
 * What a finding says about something public that became `@private`. `used` is how plugin code names it.
 */
export function becamePrivateTexts(
    label: string,
    { name, used }: { name: string; used: string },
    flags: FlagContext,
): BreakTexts {
    const tag = bcChangeTag('BecomesPrivate', flags);

    return {
        headline: `The ${label} ${name} became @private`,
        explanation: `The ${label} became @private, which removes it from the public API, so plugin code that uses ${used} can break with any later change.`,
        fix: `pattern "Becomes private". Keep it public, mark it ${tag}, and make it @private in the major.`,
        marker: scriptComment(tag),
    };
}

/** @private */
export function announcesBecomingPrivate(marker: Marker): boolean {
    return isBcChange(marker, 'BecomesPrivate');
}

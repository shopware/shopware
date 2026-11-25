/**
 * @sw-package framework
 *
 * The snapshot covers two flag states: `current`, with the major flags off, and `major`, with them on.
 * An element of the model carries the states it exists in. `read/flag-conditions.ts` decides them from the
 * feature checks around a piece of source.
 */
import { isSameFlag, versionOfFlag } from '../../feature-flags';
import { byCodePoint } from '../helpers/code-point-order';

/** @private */
export const FLAG_STATES = [
    'current',
    'major',
] as const;

/** @private */
export type FlagState = (typeof FLAG_STATES)[number];

/**
 * @private
 *
 * The flag states in which an element exists.
 */
export type FlagStates = Record<FlagState, boolean>;

/** @private */
export function perState<T>(value: (state: FlagState) => T): Record<FlagState, T> {
    return { current: value('current'), major: value('major') };
}

/** @private */
export const BOTH_STATES: FlagStates = perState(() => true);

/**
 * @private
 *
 * The major flags of one generator run. `majorFlag` is the version flag among them, such as `v6.8.0.0`,
 * and `majorVersion` the release it stands for, `v6.8.0`.
 */
export interface FlagContext {
    majorFlag: string;
    majorVersion: string;
    isMajorFlag(flag: string): boolean;
}

/** @private */
export function createFlagContext(majorFlags: readonly string[]): FlagContext {
    const version = majorFlags.map(versionOfFlag).find((candidate) => candidate !== null);

    if (!version) {
        throw new Error(`None of the major flags is a version flag such as v6.8.0.0: ${majorFlags.join(', ')}`);
    }

    const [major, minor] = version;

    return {
        majorFlag: `v${major}.${minor}.0.0`,
        majorVersion: `v${major}.${minor}.0`,
        isMajorFlag: (flag) => majorFlags.some((majorFlag) => isSameFlag(majorFlag, flag)),
    };
}

/** @private */
export function inBothStates(a: FlagStates, b: FlagStates): FlagStates {
    return perState((state) => a[state] && b[state]);
}

function inAnyState(a: FlagStates, b: FlagStates): FlagStates {
    return perState((state) => a[state] || b[state]);
}

/**
 * @private
 *
 * One item per key, sorted by key, in the flag states of every item with that key. The first item of a key
 * gives the rest of the entry.
 */
export function mergeStates<Item extends { states: FlagStates }>(items: Item[], key: (item: Item) => string): Item[] {
    const merged = new Map<string, Item>();

    items.forEach((item) => {
        const known = merged.get(key(item));

        merged.set(key(item), known ? { ...known, states: inAnyState(known.states, item.states) } : item);
    });

    return [...merged].sort(([a], [b]) => byCodePoint(a, b)).map(([, item]) => item);
}

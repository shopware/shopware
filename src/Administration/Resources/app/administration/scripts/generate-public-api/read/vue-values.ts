/**
 * @sw-package framework
 *
 * The calls of Vue that build a value a script holds, by what the value is to code that reads it.
 */

/** @private */
export const STATE_CALLS = [
    'ref',
    'shallowRef',
];

/** @private */
export const GETTER_CALLS = ['computed'];

/**
 * @private
 *
 * Every call that gives a value and no function, also when a function computes the value.
 */
export const VALUE_CALLS = [
    ...STATE_CALLS,
    ...GETTER_CALLS,
];

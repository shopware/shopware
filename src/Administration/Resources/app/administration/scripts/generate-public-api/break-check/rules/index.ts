/**
 * @sw-package framework
 *
 * The list of all rules. `break-check.ts` runs every rule in it on every component.
 */
import { BLOCK_RULES } from './block-rules';
import { MEMBER_RULES } from './member-rules';
import type { Rule } from './rule';

/**
 * @private
 *
 * Every rule of the break check, for the parts of a component built so far. A rule that is not in this list
 * never runs. `testing/expected-failures.ts` lists what is missing.
 */
export const RULES: readonly Rule[] = [
    ...BLOCK_RULES,
    ...MEMBER_RULES,
];

/**
 * @sw-package framework
 */

/**
 * Reconnects `v-if` chains that the `{% block %}` → `<sw-block>` conversion tore apart.
 *
 * The reconnection itself lives in the runtime module `src/core/factory/reconnect-cross-block-conditionals.ts`,
 * shared with the template factory, which wraps Twig blocks in `<sw-block>` at runtime and has to
 * repair the same chains. This codemod adds the one thing the runtime cannot afford: a guard
 * re-evaluates the preceding conditions, so a conversion is refused when one of them has side
 * effects. Parsing those conditions needs Babel, which is why the check stays on the tooling side.
 */

import { parseExpression } from '@babel/parser';
import { traverseFast } from '@babel/types';
import type * as t from '@babel/types';
import {
    normalizeCrossBlockConditionals as normalizeWithOptions,
    type NormalizeResult,
} from '../../../src/core/factory/reconnect-cross-block-conditionals';

const SIDE_EFFECTING_EXPRESSIONS = new Set([
    'CallExpression',
    'OptionalCallExpression',
    'AssignmentExpression',
    'UpdateExpression',
    'NewExpression',
    'AwaitExpression',
    'YieldExpression',
    'TaggedTemplateExpression',
    'SequenceExpression',
]);

/**
 * Guard insertion evaluates the preceding conditions again. Only expressions with no callable,
 * assignment, update, allocation, or async evaluation may therefore cross a generated sw-block.
 * Parse failures are conservative: a normal compiler validation pass can still accept the source,
 * but this normalizer must not claim equivalent evaluation timing for syntax it cannot inspect.
 */
function isSideEffectFreeCondition(expression: string): boolean {
    let parsed: t.Expression;

    try {
        parsed = parseExpression(expression, { plugins: ['typescript'] }) as t.Expression;
    } catch {
        return false;
    }

    let safe = true;

    traverseFast(parsed, (node) => {
        if (SIDE_EFFECTING_EXPRESSIONS.has(node.type)) {
            safe = false;
        }
    });

    return safe;
}

/**
 * Returns the markup with guard branches inserted, or the blocker when a continuation has no
 * preceding `v-if` at all — that one cannot be reconnected, only reported — or when a guard would
 * re-evaluate a side-effecting condition.
 */
function normalizeCrossBlockConditionals(body: string): NormalizeResult {
    return normalizeWithOptions(body, { isSafeCondition: isSideEffectFreeCondition });
}

export { normalizeCrossBlockConditionals, type NormalizeResult };

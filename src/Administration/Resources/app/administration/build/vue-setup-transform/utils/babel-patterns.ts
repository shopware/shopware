/**
 * @sw-package framework
 */

/**
 * Shared Babel helpers used by both the script analyzer and the template analyzer.
 *
 * Destructuring patterns appear in several places (variable declarations, slot scopes, v-for
 * aliases); this module owns the one walk over them so pattern semantics stay identical everywhere.
 */

import type { Identifier, Node as BabelNode } from '@babel/types';

/**
 * Narrow structural check for values encountered while walking untyped Babel node records.
 */
function isBabelNodeLike(value: unknown): value is BabelNode {
    return Boolean(value && typeof value === 'object' && 'type' in value && typeof value.type === 'string');
}

/**
 * Visits every identifier a binding pattern declares.
 *
 * Default values and computed keys are reads, not declarations, so they are deliberately skipped:
 * `const { label = fallback, [key]: value } = source` declares `label` and `value` only.
 */
function forEachPatternIdentifier(pattern: BabelNode | null | undefined, visit: (identifier: Identifier) => void): void {
    // e.g. array holes: `const [, second] = pair` has a null first element.
    if (!pattern) {
        return;
    }

    // e.g. `count` in `const count = 1` or as any nested pattern leaf.
    if (pattern.type === 'Identifier') {
        visit(pattern);
        return;
    }

    // e.g. `...rest` in `const [first, ...rest] = list`.
    if (pattern.type === 'RestElement') {
        forEachPatternIdentifier(pattern.argument, visit);
        return;
    }

    // e.g. `label = fallback` in `const { label = fallback } = source`; only the left side declares.
    if (pattern.type === 'AssignmentPattern') {
        forEachPatternIdentifier(pattern.left, visit);
        return;
    }

    // e.g. `const [first, second] = pair`.
    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => {
            forEachPatternIdentifier(element, visit);
        });
        return;
    }

    // e.g. `const { info, nested: { deep }, ...rest } = source`; values declare, keys do not.
    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                forEachPatternIdentifier(property.argument, visit);
                return;
            }

            forEachPatternIdentifier(property.value, visit);
        });
    }
}

/**
 * @private
 */
export { forEachPatternIdentifier, isBabelNodeLike };

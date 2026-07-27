/**
 * @sw-package framework
 */

/**
 * Decides, from an identifier's position on its parent, whether it *reads* a value.
 *
 * Both flow-analysis passes need this same judgement - the template-expression pass (which identifiers
 * does this Vue expression read from setup scope) and the setup-script rename pass (which occurrences of
 * a top-level name must be rewritten). They used to answer it separately, one keyed on the parent field
 * name and one on node identity, which meant a fix to read-detection had to be made twice.
 *
 * The positions where an identifier is *not* a read:
 * - member-access property name - the `x` in `obj.x` (a read only when computed, `obj[x]`)
 * - static object key - the `x` in `{ x: … }` or `x() {}` (a read only when computed, `{ [x]: … }`)
 * - declaration id - the name in `const x` / `function x` / `class x`
 * - function parameter name
 * - break/continue/label target
 */

import type { Node as BabelNode } from '@babel/types';

/**
 * Whether an identifier sits in a value-read position on its parent.
 *
 * Decided purely from node identity, so it works for any walk regardless of whether that walk tracks the
 * parent field name.
 */
function isValueReadPosition(node: BabelNode, parent: BabelNode | null): boolean {
    if (!parent) {
        return true;
    }

    // e.g. `source.count` reads `source` but not `count`; `source[count]` reads both.
    if (parent.type === 'MemberExpression' || parent.type === 'OptionalMemberExpression') {
        return parent.property !== node || Boolean(parent.computed);
    }

    // e.g. `{ count: value }` reads `value` but not the static key `count`; `{ [count]: value }` reads both.
    if (parent.type === 'ObjectProperty') {
        return parent.value === node || Boolean(parent.computed);
    }

    // e.g. `{ count() {} }` does not read the method name `count`.
    if (parent.type === 'ObjectMethod') {
        return parent.key !== node || Boolean(parent.computed);
    }

    // e.g. `const count = 1` or `function count() {}` declare `count` rather than reading it.
    if (
        parent.type === 'VariableDeclarator' ||
        parent.type === 'FunctionDeclaration' ||
        parent.type === 'FunctionExpression' ||
        parent.type === 'ClassDeclaration' ||
        parent.type === 'ClassExpression'
    ) {
        return parent.id !== node;
    }

    // e.g. `(count) => count * 2` declares the parameter `count`; the body reference is handled elsewhere.
    const parameters = (parent as { params?: unknown }).params;

    if (Array.isArray(parameters) && parameters.includes(node)) {
        return false;
    }

    // e.g. `break outer` targets a label rather than reading a binding.
    if (parent.type === 'BreakStatement' || parent.type === 'ContinueStatement' || parent.type === 'LabeledStatement') {
        return parent.label !== node;
    }

    return true;
}

/**
 * @private
 */
export { isValueReadPosition };

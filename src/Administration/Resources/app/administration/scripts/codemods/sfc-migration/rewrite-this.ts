/**
 * @sw-package framework
 */

/**
 * The scope-aware `this.*` rewrite pass. Walks collected member functions and overwrites every
 * component-bound `this` reference in the MagicString: data/computed → `x.value`, props →
 * `props.x`, methods/injects → `x`, instance properties via the INSTANCE_PROPS table. References
 * the tables cannot map become TODO entries — never a wrong rewrite.
 *
 * Every rewrite emits a bare root identifier, so it is only correct while no local binding of that
 * name is in scope at the emit site: `onChange(perPage) { this.perPage = perPage; }` must not become
 * `perPage.value = perPage`. The `LocalScope` chain carries the enclosing functions' own bindings so
 * a shadowed reference becomes a TODO instead.
 */

import type * as t from '@babel/types';
import { INSTANCE_PROPS, SKIP_INSTANCE_PROPS } from './tables';
import {
    type Ctx,
    type FnLike,
    type LocalScope,
    FUNCTION_TYPES,
    IDENTIFIER,
    functionScope,
    isShadowed,
    isThisMember,
    memberName,
    overwrite,
    raw,
    todo,
    visitChildren,
} from './ast';

/**
 * Rewrites every component-bound `this.*` inside `node`. `thisIsComponent` is false inside nested
 * non-arrow functions, whose `this` is not the component — those references become TODOs instead of
 * wrong rewrites. `scope` is the chain of local bindings the enclosing functions declare.
 */
function rewriteThis(ctx: Ctx, node: t.Node, thisIsComponent: boolean, scope: LocalScope | null): void {
    // `this.$emit('event', ...)`: infer the emits declaration from literal event names.
    if (
        node.type === 'CallExpression' &&
        thisIsComponent &&
        isThisMember(node.callee) &&
        memberName(node.callee) === '$emit'
    ) {
        const event = node.arguments[0];

        if (event && event.type === 'StringLiteral') {
            if (!ctx.inferredEmits.includes(event.value)) {
                ctx.inferredEmits.push(event.value);
            }
        } else {
            todo(ctx, 'dynamic $emit event name', raw(ctx, node));
        }
    }

    // `this.$refs.x` → `x.value` (handled on the outer member so the ref name is known).
    if (node.type === 'MemberExpression' && isThisMember(node.object) && memberName(node.object) === '$refs') {
        if (!thisIsComponent) {
            todo(ctx, '`this.$refs` inside a nested function keeps its own `this`', raw(ctx, node));
            return;
        }

        const refName = memberName(node);

        if (refName && IDENTIFIER.test(refName) && !ctx.bindings.has(refName) && !isShadowed(scope, refName)) {
            ctx.templateRefs.add(refName);
            overwrite(ctx, node, `${refName}.value`);
            return;
        }

        // The shadowed case must not register the ref either — transform-script would emit a
        // `const x = ref(null)` that nothing ever assigns.
        todo(
            ctx,
            refName
                ? isShadowed(scope, refName)
                    ? `template ref '${refName}' is shadowed by a local binding`
                    : `template ref '${refName}' collides with an existing binding`
                : 'dynamic this.$refs access',
            raw(ctx, node),
        );

        if (node.computed) {
            rewriteThis(ctx, node.property, thisIsComponent, scope);
        }

        return;
    }

    if (isThisMember(node)) {
        rewriteThisMember(ctx, node, thisIsComponent, scope);
        return;
    }

    if (node.type === 'ThisExpression') {
        todo(ctx, thisIsComponent ? 'bare `this` usage' : '`this` inside a nested function');
        return;
    }

    // Nested non-arrow functions rebind `this`; arrows inherit the current binding.
    const rebindsThis =
        node.type === 'FunctionExpression' ||
        node.type === 'FunctionDeclaration' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod';
    const childScope = FUNCTION_TYPES.has(node.type) ? functionScope(node, scope) : scope;

    visitChildren(node, (child) => rewriteThis(ctx, child, rebindsThis ? false : thisIsComponent, childScope));
}

function rewriteThisMember(ctx: Ctx, node: t.MemberExpression, thisIsComponent: boolean, scope: LocalScope | null): void {
    const name = memberName(node);

    if (!name) {
        todo(ctx, 'dynamic `this[...]` access', raw(ctx, node));
        rewriteThis(ctx, node.property, thisIsComponent, scope);
        return;
    }

    if (!thisIsComponent) {
        todo(ctx, `\`this.${name}\` inside a nested function keeps its own \`this\``);
        return;
    }

    if (SKIP_INSTANCE_PROPS.has(name)) {
        ctx.blockers.add(`this.${name}`);
        return;
    }

    const instanceProp = INSTANCE_PROPS[name];

    if (instanceProp) {
        // Bail before registering the helper, so a shadowed reference does not declare an unused one.
        if (isShadowed(scope, instanceProp.replacement)) {
            todo(ctx, `this.${name} is shadowed by a local binding`);
            return;
        }

        if (instanceProp.helper) {
            ctx.helpers.add(instanceProp.helper);
        }

        overwrite(ctx, node, instanceProp.replacement);
        return;
    }

    const kind = ctx.bindings.get(name);

    if (kind === undefined) {
        todo(ctx, `unmapped this.${name}`);
        return;
    }

    // Props resolve through the `props` object, so only that name can shadow them.
    if (isShadowed(scope, kind === 'prop' ? 'props' : name)) {
        todo(ctx, `this.${name} is shadowed by a local binding`);
        return;
    }

    if (kind === 'prop') {
        ctx.helpers.add('props');
        overwrite(ctx, node, `props.${name}`);
    } else if (kind === 'data' || kind === 'computed') {
        overwrite(ctx, node, `${name}.value`);
    } else {
        overwrite(ctx, node, name);
    }
}

/**
 * Walks a member function's children with component `this` semantics. Arrow-function members never
 * had component `this` in the Options API either, so their contents are treated as foreign.
 */
function rewriteMemberFn(ctx: Ctx, fn: FnLike): void {
    const thisIsComponent = fn.fnNode.type !== 'ArrowFunctionExpression';
    const scope = functionScope(fn.fnNode, null);

    visitChildren(fn.fnNode, (child) => rewriteThis(ctx, child, thisIsComponent, scope));
}

export { rewriteThis, rewriteMemberFn };

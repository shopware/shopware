/**
 * @sw-package framework
 */

/**
 * The scope-aware `this.*` rewrite pass: data/computed → `x.value`, props → `props.x`,
 * methods/injects → `x`, instance properties via INSTANCE_PROPS. What the tables cannot map becomes
 * a TODO, never a wrong rewrite.
 *
 * A rewrite emits a bare identifier, so it is only correct while no local of that name is in scope:
 * `onChange(perPage) { this.perPage = perPage; }` must not become `perPage.value = perPage`. The
 * scope walk stops at the pass root, above which live the component's own members.
 */

import type { NodePath } from '@babel/core';
import type * as t from '@babel/types';
import { INSTANCE_PROPS, SKIP_INSTANCE_PROPS } from './tables';
import {
    type Ctx,
    type FnLike,
    IDENTIFIER,
    bindingName,
    isThisMember,
    memberName,
    overwrite,
    report,
    reportFix,
} from './ast';

type Scope = NodePath['scope'];

/** Nested non-arrow functions rebind `this`; a class owns its `this` in every position. */
const REBINDS_THIS = new Set<string>([
    'FunctionExpression',
    'FunctionDeclaration',
    'ObjectMethod',
    'ClassMethod',
    'ClassPrivateMethod',
    'ClassDeclaration',
    'ClassExpression',
]);

/**
 * Second arguments of `$t` that mean the same to legacy `$t` and Composition `t()`: named values, a
 * list, or a count that provably is a number — anything that could be a string may be a locale.
 */
function isPortableI18nArgument(node: t.Node): boolean {
    switch (node.type) {
        case 'ObjectExpression':
        case 'ArrayExpression':
        case 'NumericLiteral':
            return true;
        case 'UnaryExpression':
            return (node.operator === '-' || node.operator === '+') && isPortableI18nArgument(node.argument);
        case 'ConditionalExpression':
            return isPortableI18nArgument(node.consequent) && isPortableI18nArgument(node.alternate);
        case 'TSAsExpression':
        case 'TSNonNullExpression':
            return isPortableI18nArgument(node.expression);
        default:
            return false;
    }
}

/**
 * The legacy vue-i18n call shapes Composition `t()` reads differently: a locale as `$t`'s second
 * argument (read as a default message) and `$tc`'s third `values` argument (read as options). Both
 * need a call rewrite, not the rename INSTANCE_PROPS applies.
 */
function legacyI18nShape(call: t.CallExpression, name: string): { reason: string; explanation: string } | null {
    if (name === '$t' && call.arguments.length >= 2 && !isPortableI18nArgument(call.arguments[1])) {
        return {
            reason: 'this.$t(key, locale) is left as authored and does not run in setup',
            explanation:
                'Composition t() would read the locale as a default message; rewrite the call as t(key, values, { locale })',
        };
    }

    if (name === '$tc' && call.arguments.length >= 3) {
        return {
            reason: 'this.$tc(key, choice, values) is left as authored and does not run in setup',
            explanation: 'Composition t() expects options in the third argument; rewrite the call as t(key, values, choice)',
        };
    }

    return null;
}

/** The boundaries of one rewrite pass, so the visitor is a pure function of the visited path. */
type Pass = {
    ctx: Ctx;
    /** `this` semantics at the pass root — ancestors below `stopAt` can only revoke it. */
    baseIsComponent: boolean;
    /** Ancestor walks stop here: the first path whose `this` binding the pass does not own. */
    stopAt: NodePath | null;
    /** Scope walks stop here: bindings at or above it are the component's, not a local's. */
    outerScope: Scope | null;
};

function thisIsComponent(pass: Pass, path: NodePath): boolean {
    if (!pass.baseIsComponent) {
        return false;
    }

    for (let ancestor = path.parentPath; ancestor && ancestor !== pass.stopAt; ancestor = ancestor.parentPath) {
        if (REBINDS_THIS.has(ancestor.node.type)) {
            return false;
        }
    }

    return true;
}

function isShadowed(pass: Pass, path: NodePath, name: string): boolean {
    for (let scope: Scope | undefined = path.scope; scope && scope !== pass.outerScope; scope = scope.parent) {
        if (scope.hasOwnBinding(name)) {
            return true;
        }
    }

    return false;
}

/** Handled on the outer member, where the ref name is known. */
function rewriteRefsAccess(pass: Pass, node: t.MemberExpression, path: NodePath, isComponent: boolean): boolean {
    const { ctx } = pass;

    if (!isComponent) {
        report(ctx, 'todo', '`this.$refs` inside a nested function keeps its own `this`', node);
        return false;
    }

    const refName = memberName(node);

    if (refName && IDENTIFIER.test(refName) && !ctx.bindings.has(refName) && !isShadowed(pass, path, refName)) {
        ctx.templateRefs.add(refName);
        overwrite(ctx, node, `${refName}.value`);
        return false;
    }

    // Not registered either: nothing would ever assign the `ref(null)`.
    report(
        ctx,
        'todo',
        refName
            ? isShadowed(pass, path, refName)
                ? `template ref '${refName}' is shadowed by a local binding`
                : `template ref '${refName}' collides with an existing binding`
            : 'dynamic this.$refs access',
        node,
    );

    return node.computed;
}

function rewriteThisMember(pass: Pass, node: t.MemberExpression, path: NodePath, isComponent: boolean): boolean {
    const { ctx } = pass;
    const name = memberName(node);

    if (!name) {
        report(ctx, 'todo', 'dynamic `this[...]` access', node);
        return true;
    }

    if (!isComponent) {
        report(ctx, 'todo', `\`this.${name}\` inside a nested function keeps its own \`this\``);
        return false;
    }

    if (SKIP_INSTANCE_PROPS.has(name)) {
        report(ctx, 'skip', `this.${name}`);
        return false;
    }

    const instanceProp = INSTANCE_PROPS[name];

    if (instanceProp) {
        // Checked before the helper is registered, so a shadowed reference declares no unused one.
        if (isShadowed(pass, path, instanceProp.replacement)) {
            report(ctx, 'todo', `this.${name} is shadowed by a local binding`);
            return false;
        }

        if (instanceProp.helper) {
            ctx.helpers.add(instanceProp.helper);
        }

        overwrite(ctx, node, instanceProp.replacement);
        return false;
    }

    const kind = ctx.bindings.get(name);

    if (kind === undefined) {
        report(ctx, 'todo', `unmapped this.${name}`);
        return false;
    }

    const binding = bindingName(ctx, name);

    // Props resolve through the `props` object, so only that name can shadow them.
    if (isShadowed(pass, path, kind === 'prop' ? 'props' : binding)) {
        report(ctx, 'todo', `this.${name} is shadowed by a local binding`);
        return false;
    }

    if (kind === 'prop') {
        ctx.helpers.add('props');
        overwrite(ctx, node, `props.${name}`);
    } else if (kind === 'data' || kind === 'computed') {
        overwrite(ctx, node, `${binding}.value`);
    } else {
        overwrite(ctx, node, binding);
    }

    return false;
}

/**
 * Handles one path and reports whether its subtree still needs visiting. A consumed reference
 * returns false: its receiver (`this`, or `this.$refs`) belongs to the member handled here and must
 * not be reported a second time.
 */
function visit(pass: Pass, path: NodePath): boolean {
    const { ctx } = pass;
    const { node, parent } = path;
    const isReceiver = parent.type === 'MemberExpression' && parent.object === node;

    if (isReceiver && (node.type === 'ThisExpression' || (isThisMember(node) && memberName(node) === '$refs'))) {
        return false;
    }

    const isComponent = thisIsComponent(pass, path);

    if (node.type === 'CallExpression' && isComponent && isThisMember(node.callee) && memberName(node.callee) === '$emit') {
        const event = node.arguments[0];

        if (event && event.type === 'StringLiteral') {
            if (!ctx.inferredEmits.includes(event.value)) {
                ctx.inferredEmits.push(event.value);
            }
        } else {
            report(ctx, 'todo', 'dynamic $emit event name', node);
        }
    }

    if (node.type === 'CallExpression' && isComponent && isThisMember(node.callee)) {
        const calleeName = memberName(node.callee);
        const legacyShape = calleeName === null ? null : legacyI18nShape(node, calleeName);

        if (legacyShape !== null) {
            reportFix(ctx, legacyShape.reason, legacyShape.explanation, node);
        }
    }

    if (node.type === 'MemberExpression' && isThisMember(node.object) && memberName(node.object) === '$refs') {
        return rewriteRefsAccess(pass, node, path, isComponent);
    }

    if (isThisMember(node)) {
        const name = memberName(node);

        // The callee of a legacy i18n call stays as authored for a human to decide; its arguments
        // keep rewriting.
        if (
            isComponent &&
            name !== null &&
            parent.type === 'CallExpression' &&
            parent.callee === node &&
            legacyI18nShape(parent, name) !== null
        ) {
            return false;
        }

        return rewriteThisMember(pass, node, path, isComponent);
    }

    if (node.type === 'ThisExpression') {
        report(ctx, 'todo', isComponent ? 'bare `this` usage' : '`this` inside a nested function');
        return false;
    }

    return true;
}

/**
 * A spliced-in node is itself part of the rewritten region, so its own `this` rebinding applies; a
 * member function *is* the component frame and only its interior can rebind.
 */
function runPass(ctx: Ctx, root: NodePath, baseIsComponent: boolean, rootIsRewritten: boolean): void {
    const pass: Pass = {
        ctx,
        baseIsComponent,
        stopAt: rootIsRewritten ? root.parentPath : root,
        outerScope: root.parentPath?.scope ?? null,
    };

    if (rootIsRewritten && !visit(pass, root)) {
        return;
    }

    root.traverse({
        enter(path) {
            if (!visit(pass, path)) {
                path.skip();
            }
        },
    });
}

function rewriteThis(ctx: Ctx, node: t.Node, thisIsComponentAtNode: boolean): void {
    const root = ctx.paths.get(node);

    if (root) {
        runPass(ctx, root, thisIsComponentAtNode, true);
    }
}

/** An arrow-function member never had component `this`, so its contents are foreign. */
function rewriteMemberFn(ctx: Ctx, fn: FnLike): void {
    const root = ctx.paths.get(fn.fnNode);

    if (root) {
        runPass(ctx, root, fn.fnNode.type !== 'ArrowFunctionExpression', false);
    }
}

export { rewriteThis, rewriteMemberFn };

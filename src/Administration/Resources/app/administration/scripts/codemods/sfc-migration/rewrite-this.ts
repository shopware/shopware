/**
 * @sw-package framework
 */

/**
 * The scope-aware `this.*` rewrite pass. Traverses collected member functions with @babel/traverse
 * and overwrites every component-bound `this` reference in the MagicString: data/computed →
 * `x.value`, props → `props.x`, methods/injects → `x`, instance properties via the INSTANCE_PROPS
 * table. References the tables cannot map become TODO entries — never a wrong rewrite.
 *
 * Every rewrite emits a bare root identifier, so it is only correct while no local binding of that
 * name is in scope at the emit site: `onChange(perPage) { this.perPage = perPage; }` must not become
 * `perPage.value = perPage`. Babel's scope chain answers that, walked only up to the scope
 * surrounding the pass root — above it live the component's own members, which are not shadowing.
 */

import type { NodePath } from '@babel/core';
import type * as t from '@babel/types';
import { INSTANCE_PROPS, SKIP_INSTANCE_PROPS } from './tables';
import { type Ctx, type FnLike, IDENTIFIER, isThisMember, memberName, overwrite, report } from './ast';

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

/** True when a bare `name` emitted here would resolve to a local binding instead of the setup one. */
function isShadowed(pass: Pass, path: NodePath, name: string): boolean {
    for (let scope: Scope | undefined = path.scope; scope && scope !== pass.outerScope; scope = scope.parent) {
        if (scope.hasOwnBinding(name)) {
            return true;
        }
    }

    return false;
}

/** `this.$refs.x` → `x.value`, handled on the outer member so the ref name is known. */
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

    // The shadowed case must not register the ref either — transform-script would emit a
    // `const x = ref(null)` that nothing ever assigns.
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
        // Bail before registering the helper, so a shadowed reference does not declare an unused one.
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

    // Props resolve through the `props` object, so only that name can shadow them.
    if (isShadowed(pass, path, kind === 'prop' ? 'props' : name)) {
        report(ctx, 'todo', `this.${name} is shadowed by a local binding`);
        return false;
    }

    if (kind === 'prop') {
        ctx.helpers.add('props');
        overwrite(ctx, node, `props.${name}`);
    } else if (kind === 'data' || kind === 'computed') {
        overwrite(ctx, node, `${name}.value`);
    } else {
        overwrite(ctx, node, name);
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

    // `this.$emit('event', ...)`: infer the emits declaration from literal event names.
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

    if (node.type === 'MemberExpression' && isThisMember(node.object) && memberName(node.object) === '$refs') {
        return rewriteRefsAccess(pass, node, path, isComponent);
    }

    if (isThisMember(node)) {
        return rewriteThisMember(pass, node, path, isComponent);
    }

    if (node.type === 'ThisExpression') {
        report(ctx, 'todo', isComponent ? 'bare `this` usage' : '`this` inside a nested function');
        return false;
    }

    return true;
}

/**
 * `rootIsRewritten` distinguishes the two entry points: a spliced-in node is itself part of the
 * rewritten region, so its own `this` rebinding applies, whereas a member function *is* the
 * component frame and only its interior can rebind.
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

/** Rewrites every component-bound `this.*` inside `node`, which is spliced in as-is. */
function rewriteThis(ctx: Ctx, node: t.Node, thisIsComponentAtNode: boolean): void {
    const root = ctx.paths.get(node);

    if (root) {
        runPass(ctx, root, thisIsComponentAtNode, true);
    }
}

/**
 * Rewrites a member function's body with component `this` semantics. Arrow-function members never
 * had component `this` in the Options API either, so their contents are treated as foreign.
 */
function rewriteMemberFn(ctx: Ctx, fn: FnLike): void {
    const root = ctx.paths.get(fn.fnNode);

    if (root) {
        runPass(ctx, root, fn.fnNode.type !== 'ArrowFunctionExpression', false);
    }
}

export { rewriteThis, rewriteMemberFn };

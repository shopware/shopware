/**
 * @sw-package framework
 */

/**
 * The one call the codemod rewrites rather than an option: `ExtensionAPI.publishData()`.
 *
 * It is a plain global call sitting anywhere in the component's own code, and it takes the instance
 * as `scope` so it can read the published value by path, write app updates back through that path,
 * and park its teardown on a member a global mixin owns — none of which a `<script setup>` component
 * provides. `usePublishedData(id, ref)` takes the ref instead.
 *
 * The call is rewritten where it stands. Every composable API it needs is available inside a
 * lifecycle hook as well as at setup top level, so nothing has to be hoisted and the order the
 * component published its data sets in is preserved.
 */

import type * as t from '@babel/types';
import { traverseFast } from '@babel/types';
import { type Ctx, bindingName, keyName, overwrite, raw, report } from './ast';

const PUBLISH_DATA_CALLEE = 'Shopware.ExtensionAPI.publishData';

/** The dotted source form of a callee, or null for any shape that is not a plain member chain. */
function calleeText(callee: t.Node): string | null {
    if (callee.type === 'Identifier') {
        return callee.name;
    }

    if (callee.type !== 'MemberExpression' || callee.computed || callee.property.type !== 'Identifier') {
        return null;
    }

    const object = calleeText(callee.object);

    return object === null ? null : `${object}.${callee.property.name}`;
}

type PublishArguments = { id: string; path: string; extra: t.ObjectProperty[] };

/** The three arguments the composable needs, or null when the call is not in the shape it expects. */
function readArguments(node: t.CallExpression): PublishArguments | null {
    const [argument] = node.arguments;

    if (node.arguments.length !== 1 || !argument || argument.type !== 'ObjectExpression') {
        return null;
    }

    let id: string | null = null;
    let path: string | null = null;
    let hasInstanceScope = false;
    const extra: t.ObjectProperty[] = [];

    for (const property of argument.properties) {
        const name = property.type === 'SpreadElement' ? null : keyName(property);

        if (property.type !== 'ObjectProperty' || name === null) {
            return null;
        }

        if (name === 'id' && property.value.type === 'StringLiteral') {
            id = property.value.value;
        } else if (name === 'path' && property.value.type === 'StringLiteral') {
            path = property.value.value;
        } else if (name === 'scope' && property.value.type === 'ThisExpression') {
            hasInstanceScope = true;
        } else if (name === 'deprecated' || name === 'deprecationMessage' || name === 'showDoubleRegistrationError') {
            extra.push(property);
        } else {
            return null;
        }
    }

    return id !== null && path !== null && hasInstanceScope ? { id, path, extra } : null;
}

/**
 * Rewrites every recognised `publishData()` call in the component's options and reports whether any
 * were rewritten, so the caller knows whether to import the composable. Runs before the `this`
 * rewrite, and records its ranges so that pass leaves the replaced text alone.
 */
function rewritePublishedData(ctx: Ctx, options: t.ObjectExpression): boolean {
    const calls: { node: t.CallExpression; args: PublishArguments }[] = [];
    let rewritten = 0;

    traverseFast(options, (node) => {
        if (node.type !== 'CallExpression' || calleeText(node.callee) !== PUBLISH_DATA_CALLEE) {
            return;
        }

        const args = readArguments(node);

        // A path with more than one segment addressed something inside the published value, which a
        // ref cannot express as its own source.
        if (args === null || args.path.includes('.')) {
            report(ctx, 'todo', 'unsupported ExtensionAPI.publishData() call', node);

            return;
        }

        calls.push({ node, args });
    });

    for (const { node, args } of calls) {
        const kind = ctx.bindings.get(args.path);

        if (kind !== 'data' && kind !== 'computed') {
            report(ctx, 'todo', `publishData path '${args.path}' is not a component data or computed member`, node);

            continue;
        }

        const options_ = args.extra.length > 0 ? `, { ${args.extra.map((property) => raw(ctx, property)).join(', ')} }` : '';

        overwrite(ctx, node, `usePublishedData('${args.id}', ${bindingName(ctx, args.path)}${options_})`);
        ctx.rewrittenRanges.push({ start: node.start as number, end: node.end as number });
        rewritten += 1;
    }

    return rewritten > 0;
}

export { rewritePublishedData };

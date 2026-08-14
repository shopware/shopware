/**
 * @sw-package framework
 */

/**
 * The shared transform context plus generic AST/text helpers. Nothing in here encodes conversion
 * policy — all extracted text is verbatim source (with rewrites applied via the MagicString),
 * and every helper is agnostic to which option it serves.
 */

import { VISITOR_KEYS } from '@babel/types';
import type * as t from '@babel/types';
import type MagicString from 'magic-string';
import type { MemberKind, HelperName, TodoEntry } from './tables';

type Ctx = {
    source: string;
    ms: MagicString;
    componentName: string;
    bindings: Map<string, MemberKind>;
    templateRefs: Set<string>;
    helpers: Set<HelperName>;
    inferredEmits: string[];
    todos: TodoEntry[];
    blockers: Set<string>;
};

type FnLike = {
    fnNode: t.ObjectMethod | t.FunctionExpression | t.ArrowFunctionExpression;
    params: t.Node[];
    body: t.BlockStatement | t.Expression;
    async: boolean;
};

const IDENTIFIER = /^[A-Za-z_$][A-Za-z0-9_$]*$/;

/** Extracted range text WITH all rewrites applied so far. */
function snip(ctx: Ctx, node: t.Node): string {
    return ctx.ms.snip(node.start as number, node.end as number).toString();
}

/** Original, unrewritten source text of a node (used for TODO comments). */
function raw(ctx: Ctx, node: t.Node): string {
    return ctx.source.slice(node.start as number, node.end as number);
}

function overwrite(ctx: Ctx, node: t.Node, text: string): void {
    ctx.ms.overwrite(node.start as number, node.end as number, text);
}

function todo(ctx: Ctx, reason: string, code?: string): void {
    if (!ctx.todos.some((entry) => entry.reason === reason && entry.code === code)) {
        ctx.todos.push({ reason, code });
    }
}

function keyName(prop: t.ObjectMethod | t.ObjectProperty): string | null {
    if (prop.computed) {
        return null;
    }

    if (prop.key.type === 'Identifier') {
        return prop.key.name;
    }

    if (prop.key.type === 'StringLiteral') {
        return prop.key.value;
    }

    return null;
}

/**
 * Normalizes an object member to its function, regardless of `foo() {}` / `foo: function () {}` /
 * `foo: () => {}` authoring style.
 */
function asFunction(prop: t.ObjectMethod | t.ObjectProperty | t.SpreadElement): FnLike | null {
    if (prop.type === 'ObjectMethod' && prop.kind === 'method' && !prop.generator) {
        return { fnNode: prop, params: prop.params, body: prop.body, async: prop.async };
    }

    if (
        prop.type === 'ObjectProperty' &&
        (prop.value.type === 'FunctionExpression' || prop.value.type === 'ArrowFunctionExpression') &&
        !prop.value.generator
    ) {
        return { fnNode: prop.value, params: prop.value.params, body: prop.value.body, async: prop.value.async };
    }

    return null;
}

function visitChildren(node: t.Node, visit: (child: t.Node) => void): void {
    const keys = VISITOR_KEYS[node.type] ?? [];

    for (const key of keys) {
        const child = (node as unknown as Record<string, unknown>)[key];

        if (Array.isArray(child)) {
            for (const entry of child) {
                if (entry && typeof (entry as t.Node).type === 'string') {
                    visit(entry as t.Node);
                }
            }
        } else if (child && typeof (child as t.Node).type === 'string') {
            visit(child as t.Node);
        }
    }
}

function isThisMember(node: t.Node): node is t.MemberExpression {
    return node.type === 'MemberExpression' && node.object.type === 'ThisExpression';
}

function memberName(node: t.MemberExpression): string | null {
    if (!node.computed && node.property.type === 'Identifier') {
        return node.property.name;
    }

    if (node.computed && node.property.type === 'StringLiteral') {
        return node.property.value;
    }

    return null;
}

/** Renders a member function as an arrow function, body and params verbatim. */
function arrowText(ctx: Ctx, fn: FnLike): string {
    const params =
        fn.params.length > 0
            ? snip(ctx, {
                  start: fn.params[0].start,
                  end: fn.params[fn.params.length - 1].end,
              } as t.Node)
            : '';
    const asyncPrefix = fn.async ? 'async ' : '';

    return `${asyncPrefix}(${params}) => ${snip(ctx, fn.body)}`;
}

/** Text of a block body's statements, without the surrounding braces. */
function blockInner(ctx: Ctx, fn: FnLike): string {
    if (fn.body.type !== 'BlockStatement') {
        return `${snip(ctx, fn.body)};`;
    }

    return ctx.ms
        .snip((fn.body.start as number) + 1, (fn.body.end as number) - 1)
        .toString()
        .trim();
}

/** Resolves the component options object from the default export's declaration. */
function unwrapOptions(declaration: t.Node): t.ObjectExpression | null {
    if (declaration.type === 'ObjectExpression') {
        return declaration;
    }

    if (declaration.type === 'TSAsExpression' || declaration.type === 'TSSatisfiesExpression') {
        return unwrapOptions(declaration.expression);
    }

    if (declaration.type === 'CallExpression' && declaration.arguments.length >= 1) {
        const callee = declaration.callee;
        const calleeName =
            callee.type === 'Identifier'
                ? callee.name
                : callee.type === 'MemberExpression' && callee.property.type === 'Identifier'
                  ? callee.property.name
                  : null;

        if (
            (calleeName === 'wrapComponentConfig' || calleeName === 'defineComponent') &&
            declaration.arguments[0].type === 'ObjectExpression'
        ) {
            return declaration.arguments[0];
        }
    }

    return null;
}

export {
    type Ctx,
    type FnLike,
    IDENTIFIER,
    snip,
    raw,
    overwrite,
    todo,
    keyName,
    asFunction,
    visitChildren,
    isThisMember,
    memberName,
    arrowText,
    blockInner,
    unwrapOptions,
};

/**
 * @sw-package framework
 */

/**
 * The shared transform context plus generic AST/text helpers. Nothing in here encodes conversion
 * policy — all extracted text is verbatim source (with rewrites applied via the MagicString),
 * and every helper is agnostic to which option it serves.
 */

import type { NodePath } from '@babel/core';
import type * as t from '@babel/types';
import { traverseFast } from '@babel/types';
import type MagicString from 'magic-string';
import type { MemberKind, HelperName, ReportKind, TodoEntry } from './tables';

type Ctx = {
    source: string;
    ms: MagicString;
    /** Every parsed node keyed to its @babel/traverse path, so the rewrite pass can ask for scope. */
    paths: Map<t.Node, NodePath>;
    componentName: string;
    bindings: Map<string, MemberKind>;
    /** Members whose setup binding is not named after the member (composable collision renames). */
    renamedBindings: Map<string, string>;
    /** Identifiers the converted template reads, so members only it uses still get a binding. */
    templateIdentifiers: ReadonlySet<string>;
    /** Camelized component tags the converted template renders; a binding of that name shadows one. */
    templateComponentTags: ReadonlySet<string>;
    templateRefs: Set<string>;
    helpers: Set<HelperName>;
    inferredEmits: string[];
    /** Written through `report()`; a single `skip` entry refuses the component outright. */
    reports: (TodoEntry & { kind: ReportKind })[];
};

type FnLike = {
    fnNode: t.ObjectMethod | t.FunctionExpression | t.ArrowFunctionExpression;
    params: t.Node[];
    body: t.BlockStatement | t.Expression;
    async: boolean;
    generator: boolean;
    leadingComments?: t.Comment[];
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

/** Recorded once per reason+code pair, so a repeated shape leaves a single entry. */
function pushReport(ctx: Ctx, entry: TodoEntry & { kind: ReportKind }): void {
    if (!ctx.reports.some((existing) => existing.reason === entry.reason && existing.code === entry.code)) {
        ctx.reports.push(entry);
    }
}

/**
 * The single channel for "I could not convert this". A `skip` refuses the whole component, a `todo`
 * keeps the draft and leaves a comment quoting `node`'s original source.
 */
function report(ctx: Ctx, kind: ReportKind, reason: string, node?: t.Node): void {
    pushReport(ctx, { kind, reason, code: node ? raw(ctx, node) : undefined });
}

/** A TODO about code the reader has to write, because the draft does not run as it stands. */
function reportFix(ctx: Ctx, reason: string, explanation: string, node?: t.Node): void {
    pushReport(ctx, { kind: 'todo', mode: 'FIX', reason, explanation, code: node ? raw(ctx, node) : undefined });
}

/**
 * A TODO about the emitted code as a whole rather than about one site in it: the conversion is
 * complete, what it means is what the checks ask the reader to confirm.
 */
function reportReview(ctx: Ctx, reason: string, explanation: string, checks: string[]): void {
    pushReport(ctx, { kind: 'todo', mode: 'VERIFY', reason, explanation, checks });
}

/**
 * A VERIFY TODO about one declaration the caller emits itself. It is returned rather than only
 * recorded, because the section that writes that declaration is the one that renders the block.
 */
function reportAtDeclaration(ctx: Ctx, reason: string, explanation: string): TodoEntry {
    const entry: TodoEntry & { kind: ReportKind } = {
        kind: 'todo',
        mode: 'VERIFY',
        reason,
        explanation,
        anchored: true,
    };

    pushReport(ctx, entry);

    return entry;
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
    if (prop.type === 'ObjectMethod' && prop.kind === 'method') {
        return {
            fnNode: prop,
            params: prop.params,
            body: prop.body,
            async: prop.async ?? false,
            generator: prop.generator ?? false,
            leadingComments: prop.leadingComments ?? undefined,
        };
    }

    if (
        prop.type === 'ObjectProperty' &&
        (prop.value.type === 'FunctionExpression' || prop.value.type === 'ArrowFunctionExpression')
    ) {
        return {
            fnNode: prop.value,
            params: prop.value.params,
            body: prop.value.body,
            async: prop.value.async ?? false,
            generator: prop.value.generator ?? false,
            leadingComments: prop.leadingComments ?? prop.value.leadingComments ?? undefined,
        };
    }

    return null;
}

function isThisMember(node: t.Node): node is t.MemberExpression {
    return node.type === 'MemberExpression' && node.object.type === 'ThisExpression';
}

/**
 * The setup binding a component member resolves to. Equal to the member name except where a
 * composable member had to be renamed around a name another declaration already claims.
 */
function bindingName(ctx: Ctx, member: string): string {
    return ctx.renamedBindings.get(member) ?? member;
}

/**
 * Every `this.<member>` name read inside `node`, ignoring what `this` binds at each site. Callers use
 * it to decide whether a member is referenced at all, where counting a reference that turns out to be
 * foreign only costs an unused binding — missing one would drop the member.
 */
function collectThisMemberNames(node: t.Node, names: Set<string>): void {
    traverseFast(node, (descendant) => {
        if (!isThisMember(descendant)) {
            return;
        }

        const name = memberName(descendant);

        if (name) {
            names.add(name);
        }
    });
}

/**
 * Every `this.<member>` name written to inside `node`, again ignoring what `this` binds at each site.
 * Compound assignments and `++`/`--` count: all of them need the target to be assignable.
 */
function collectAssignedThisMemberNames(node: t.Node, names: Set<string>): void {
    traverseFast(node, (descendant) => {
        const target =
            descendant.type === 'AssignmentExpression'
                ? descendant.left
                : descendant.type === 'UpdateExpression'
                  ? descendant.argument
                  : null;

        if (!target || !isThisMember(target)) {
            return;
        }

        const name = memberName(target);

        if (name) {
            names.add(name);
        }
    });
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

/**
 * Renders a collected function without changing its runtime form. In particular, arrows keep
 * concise bodies and lexical `arguments`, named function expressions keep their local name, and
 * generators/async functions retain their flags and TypeScript contracts.
 */
function arrowText(ctx: Ctx, fn: FnLike): string {
    const leadingComments = fn.leadingComments
        ?.map((comment) => ctx.source.slice(comment.start as number, comment.end as number))
        .join('\n');
    const commentPrefix = leadingComments ? `${leadingComments}\n` : '';

    if (fn.fnNode.type === 'FunctionExpression' || fn.fnNode.type === 'ArrowFunctionExpression') {
        return `${commentPrefix}${snip(ctx, fn.fnNode)}`;
    }

    const typeParameters = fn.fnNode.typeParameters ? snip(ctx, fn.fnNode.typeParameters) : '';
    const params =
        fn.params.length > 0
            ? snip(ctx, {
                  start: fn.params[0].start,
                  end: fn.params[fn.params.length - 1].end,
              } as t.Node)
            : '';
    const returnType = fn.fnNode.returnType ? snip(ctx, fn.fnNode.returnType) : '';
    const asyncPrefix = fn.async ? 'async ' : '';
    const generator = fn.generator ? '*' : '';

    return `${commentPrefix}${asyncPrefix}function${generator}${typeParameters}(${params})${returnType} ${snip(ctx, fn.body)}`;
}

const OPTIONS_WRAPPERS = new Set([
    'wrapComponentConfig',
    'defineComponent',
]);

/** Strips the type-only and grouping wrappers an expression may be authored behind. */
function unwrapExpression(node: t.Node): t.Node {
    if (
        node.type === 'TSAsExpression' ||
        node.type === 'TSSatisfiesExpression' ||
        node.type === 'TSNonNullExpression' ||
        node.type === 'TypeCastExpression' ||
        node.type === 'ParenthesizedExpression'
    ) {
        return unwrapExpression(node.expression);
    }

    return node;
}

function calleeName(callee: t.Node): string | null {
    if (callee.type === 'Identifier') {
        return callee.name;
    }

    if (callee.type === 'MemberExpression' && !callee.computed && callee.property.type === 'Identifier') {
        return callee.property.name;
    }

    return null;
}

/** Resolves the component options object from the default export's declaration. */
function unwrapOptions(declaration: t.Node): t.ObjectExpression | null {
    const expression = unwrapExpression(declaration);

    if (expression.type === 'ObjectExpression') {
        return expression;
    }

    if (
        expression.type === 'CallExpression' &&
        expression.arguments.length > 0 &&
        OPTIONS_WRAPPERS.has(calleeName(expression.callee) ?? '') &&
        expression.arguments[0].type === 'ObjectExpression'
    ) {
        return expression.arguments[0];
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
    report,
    reportFix,
    reportReview,
    reportAtDeclaration,
    keyName,
    asFunction,
    isThisMember,
    bindingName,
    collectThisMemberNames,
    collectAssignedThisMemberNames,
    memberName,
    arrowText,
    unwrapExpression,
    unwrapOptions,
};

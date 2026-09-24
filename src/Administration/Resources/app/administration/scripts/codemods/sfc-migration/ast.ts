/**
 * @sw-package framework
 */

/** The shared transform context plus generic AST/text helpers; no conversion policy. */

import type { NodePath } from '@babel/core';
import type * as t from '@babel/types';
import { traverseFast } from '@babel/types';
import type MagicString from 'magic-string';
import type { MemberKind, HelperName, ReportKind, TodoEntry } from './tables';

type Ctx = {
    source: string;
    ms: MagicString;
    /** Every parsed node keyed to its @babel/traverse path, for scope lookups. */
    paths: Map<t.Node, NodePath>;
    componentName: string;
    bindings: Map<string, MemberKind>;
    /** Composable members renamed around a collision. */
    renamedBindings: Map<string, string>;
    /** Names the converted template reads; a member only it uses still needs a binding. */
    templateIdentifiers: ReadonlySet<string>;
    /** Binding names that would shadow a tag the template renders. */
    templateComponentTags: ReadonlySet<string>;
    templateRefs: Set<string>;
    helpers: Set<HelperName>;
    inferredEmits: string[];
    /** A single `skip` entry refuses the component. */
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

function packageName(text: string): string | null {
    return /@sw-package\s+(\S+)/.exec(text)?.[1] ?? null;
}

function errorText(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}

function findExportDefault(program: t.Program): t.ExportDefaultDeclaration | undefined {
    return program.body.find(
        (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
    );
}

/** Range text with the rewrites so far applied. */
function snip(ctx: Ctx, node: t.Node): string {
    return ctx.ms.snip(node.start as number, node.end as number).toString();
}

/** Unrewritten source text, for TODO comments. */
function raw(ctx: Ctx, node: t.Node): string {
    return ctx.source.slice(node.start as number, node.end as number);
}

function overwrite(ctx: Ctx, node: t.Node, text: string): void {
    ctx.ms.overwrite(node.start as number, node.end as number, text);
}

function pushReport(ctx: Ctx, entry: TodoEntry & { kind: ReportKind }): void {
    if (!ctx.reports.some((existing) => existing.reason === entry.reason && existing.code === entry.code)) {
        ctx.reports.push(entry);
    }
}

/** A `skip` refuses the component; a `todo` keeps the draft with a comment quoting `node`. */
function report(ctx: Ctx, kind: ReportKind, reason: string, node?: t.Node): void {
    pushReport(ctx, { kind, reason, code: node ? raw(ctx, node) : undefined });
}

/** A TODO about code the reader has to write: the draft does not run as it stands. */
function reportFix(ctx: Ctx, reason: string, explanation: string, node?: t.Node): void {
    pushReport(ctx, { kind: 'todo', mode: 'FIX', reason, explanation, code: node ? raw(ctx, node) : undefined });
}

/** A TODO about the draft as a whole: it is complete, the checks ask whether it is equivalent. */
function reportReview(ctx: Ctx, reason: string, explanation: string, checks: string[]): void {
    pushReport(ctx, { kind: 'todo', mode: 'VERIFY', reason, explanation, checks });
}

/** A VERIFY TODO the caller renders above the declaration it is about. */
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

/** `foo() {}`, `foo: function () {}` and `foo: () => {}` alike. */
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

function bindingName(ctx: Ctx, member: string): string {
    return ctx.renamedBindings.get(member) ?? member;
}

/**
 * Every `this.<member>` name inside `node` that is read, or with `assigned` only those written to
 * (compound assignments and `++`/`--` included). What `this` binds at each site is ignored: counting a
 * foreign reference only costs an unused binding, missing one would drop the member.
 */
function thisMemberNames(node: t.Node, { assigned = false } = {}): Set<string> {
    const names = new Set<string>();

    traverseFast(node, (descendant) => {
        let target: t.Node | null = descendant;

        if (assigned) {
            target =
                descendant.type === 'AssignmentExpression'
                    ? descendant.left
                    : descendant.type === 'UpdateExpression'
                      ? descendant.argument
                      : null;
        }

        const name = target && isThisMember(target) ? memberName(target) : null;

        if (name) {
            names.add(name);
        }
    });

    return names;
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
 * Renders a collected function without changing its runtime form: arrows keep lexical `arguments`,
 * named function expressions their local name, generators/async functions their flags and types.
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

const OPTIONS_WRAPPERS = new Set(['wrapComponentConfig', 'defineComponent']);

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
    errorText,
    findExportDefault,
    packageName,
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
    thisMemberNames,
    memberName,
    arrowText,
    unwrapExpression,
    unwrapOptions,
};

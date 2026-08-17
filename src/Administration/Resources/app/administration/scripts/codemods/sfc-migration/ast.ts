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
    /** Members whose setup binding is not named after the member (composable collision renames). */
    renamedBindings: Map<string, string>;
    /** Identifiers the converted template reads, so members only it uses still get a binding. */
    templateIdentifiers: ReadonlySet<string>;
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

function pushTodo(ctx: Ctx, entry: TodoEntry): void {
    if (!ctx.todos.some((existing) => existing.reason === entry.reason && existing.code === entry.code)) {
        ctx.todos.push(entry);
    }
}

function todo(ctx: Ctx, reason: string, code?: string): void {
    pushTodo(ctx, { reason, code });
}

/** A TODO about code the reader has to write, because the draft does not run as it stands. */
function todoFix(ctx: Ctx, reason: string, explanation: string, code?: string): void {
    pushTodo(ctx, { reason, explanation, code, mode: 'FIX' });
}

/**
 * A TODO about the emitted code as a whole rather than about one site in it: the conversion is
 * complete, what it means is what the checks ask the reader to confirm.
 */
function todoReview(ctx: Ctx, reason: string, explanation: string, checks: string[]): void {
    pushTodo(ctx, { reason, explanation, checks, mode: 'VERIFY' });
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

/** Depth-first visit of `node` and every descendant. */
function walk(node: t.Node, visit: (node: t.Node) => void): void {
    visit(node);
    visitChildren(node, (child) => walk(child, visit));
}

/** One function's own bindings, chained to its enclosing functions. Block scopes are not modelled. */
type LocalScope = { names: Set<string>; parent: LocalScope | null };

const FUNCTION_TYPES = new Set<string>([
    'FunctionExpression',
    'FunctionDeclaration',
    'ArrowFunctionExpression',
    'ObjectMethod',
    'ClassMethod',
    'ClassPrivateMethod',
]);

/** Every name a binding pattern introduces: identifiers, destructuring, rest elements, defaults. */
function collectPatternNames(node: t.Node | null | undefined, names: Set<string>): void {
    if (!node) {
        return;
    }

    switch (node.type) {
        case 'Identifier':
            names.add(node.name);
            return;
        case 'ObjectPattern':
            for (const property of node.properties) {
                // A computed key is an expression, never a binding — only the value side binds.
                collectPatternNames(property.type === 'ObjectProperty' ? property.value : property.argument, names);
            }

            return;
        case 'ArrayPattern':
            for (const element of node.elements) {
                collectPatternNames(element, names);
            }

            return;
        case 'RestElement':
            collectPatternNames(node.argument, names);
            return;
        case 'AssignmentPattern':
            collectPatternNames(node.left, names);
            return;
        case 'TSParameterProperty':
            collectPatternNames(node.parameter, names);
            return;
        default:
            return;
    }
}

/** Collects declarations of one function body, without descending into nested function frames. */
function collectBodyBindings(node: t.Node, names: Set<string>): void {
    visitChildren(node, (child) => {
        switch (child.type) {
            case 'VariableDeclarator':
                collectPatternNames(child.id, names);
                break;
            // The name is hoisted into this frame, the interior belongs to its own frame.
            case 'FunctionDeclaration':
            case 'ClassDeclaration':
                collectPatternNames(child.id, names);
                return;
            case 'CatchClause':
                collectPatternNames(child.param, names);
                break;
            default:
                break;
        }

        if (!FUNCTION_TYPES.has(child.type) && child.type !== 'ClassBody') {
            collectBodyBindings(child, names);
        }
    });
}

/**
 * The names `fnNode` binds in its own scope: its parameters, its own name when it is a named
 * function expression, and every declaration in its body. Declarations after the reference site
 * count too — `var` shadows outright, `let`/`const` produce a temporal dead zone.
 */
function functionScope(fnNode: t.Node, parent: LocalScope | null): LocalScope {
    const names = new Set<string>();

    if ('params' in fnNode) {
        for (const param of fnNode.params) {
            collectPatternNames(param, names);
        }
    }

    if (fnNode.type === 'FunctionExpression' || fnNode.type === 'FunctionDeclaration') {
        collectPatternNames(fnNode.id, names);
    }

    if ('body' in fnNode && fnNode.body && typeof (fnNode.body as t.Node).type === 'string') {
        collectBodyBindings(fnNode.body as t.Node, names);
    }

    return { names, parent };
}

/** True when a bare `name` emitted here would resolve to a local binding instead of the setup one. */
function isShadowed(scope: LocalScope | null, name: string): boolean {
    for (let current = scope; current !== null; current = current.parent) {
        if (current.names.has(name)) {
            return true;
        }
    }

    return false;
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
    if (isThisMember(node)) {
        const name = memberName(node);

        if (name) {
            names.add(name);
        }
    }

    visitChildren(node, (child) => collectThisMemberNames(child, names));
}

/**
 * Every `this.<member>` name written to inside `node`, again ignoring what `this` binds at each site.
 * Compound assignments and `++`/`--` count: all of them need the target to be assignable.
 */
function collectAssignedThisMemberNames(node: t.Node, names: Set<string>): void {
    const target =
        node.type === 'AssignmentExpression' ? node.left : node.type === 'UpdateExpression' ? node.argument : null;

    if (target && isThisMember(target)) {
        const name = memberName(target);

        if (name) {
            names.add(name);
        }
    }

    visitChildren(node, (child) => collectAssignedThisMemberNames(child, names));
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
    type LocalScope,
    IDENTIFIER,
    FUNCTION_TYPES,
    snip,
    raw,
    overwrite,
    todo,
    todoFix,
    todoReview,
    keyName,
    asFunction,
    visitChildren,
    walk,
    collectPatternNames,
    functionScope,
    isShadowed,
    isThisMember,
    bindingName,
    collectThisMemberNames,
    collectAssignedThisMemberNames,
    memberName,
    arrowText,
    unwrapExpression,
    unwrapOptions,
};

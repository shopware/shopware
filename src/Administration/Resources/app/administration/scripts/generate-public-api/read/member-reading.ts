/**
 * @sw-package framework
 *
 * What the readers of members share: the name, line and JSDoc every member has, the notes about what a reader
 * could not read, and the signature of a function. Only the syntax is read, so code inside a function never
 * changes the result, apart from what its `return` statements hand back.
 */
import ts from 'typescript';
import { parseMarkers } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import { BOTH_STATES } from '../model/flag-states';
import type { Member, Parameter } from '../model/members';
import {
    docOf,
    isFunctionNode,
    isPrivateDoc,
    lineOf,
    memberValue,
    returnedValues,
    textOf,
    withoutTypeSyntax,
} from './script-nodes';
import type { FunctionNode } from './script-nodes';

// Enough of an expression to find it in the source, without printing a whole function into the report.
const NOTE_LENGTH = 80;

/** The name, line and JSDoc every member has. */
type Common = Pick<Member, 'name' | 'at' | 'markers' | 'isPrivate' | 'states'>;

/**
 * @private
 *
 * The `@deprecated` and `@bc-change` markers a comment holds.
 */
export function markersIn(doc: string): Marker[] {
    return parseMarkers(doc).flatMap((match): Marker[] => ('marker' in match ? [match.marker] : []));
}

/**
 * @private
 *
 * One source file while its members are read, with the notes about what could not be read.
 */
export class Reading {
    public readonly notes: string[] = [];

    public constructor(public readonly source: ts.SourceFile) {}

    public common(name: string, node: ts.Node): Common {
        const doc = docOf(node, this.source);

        return {
            name,
            at: lineOf(node, this.source),
            markers: markersIn(doc),
            isPrivate: isPrivateDoc(doc),
            states: BOTH_STATES,
        };
    }

    /** Notes what `section` holds besides the members that were read, with the start of its source text. */
    public unresolved(section: string, node: ts.Node): void {
        const text = textOf(node, this.source);

        this.notes.push(`unresolved ${section} ${text.length > NOTE_LENGTH ? `${text.slice(0, NOTE_LENGTH)}...` : text}`);
    }

    public value(member: ts.ObjectLiteralElementLike): ts.Node {
        return memberValue(member, this.source);
    }

    public text(node: ts.Node): string {
        return textOf(node, this.source);
    }
}

/** @private */
export function parametersOf(fn: FunctionNode, reading: Reading): Parameter[] {
    return fn.parameters
        .filter(({ name }) => !ts.isIdentifier(name) || name.text !== 'this')
        .map(({ name, type, initializer, questionToken, dotDotDotToken }, position) => ({
            name: ts.isIdentifier(name) ? name.text : `arg${position}`,
            pattern: ts.isIdentifier(name) ? null : reading.text(name),
            type: type === undefined ? null : reading.text(type),
            default: initializer === undefined ? { kind: 'none' } : { kind: 'value', text: reading.text(initializer) },
            optional: initializer !== undefined || questionToken !== undefined,
            rest: dotDotDotToken !== undefined,
        }));
}

/**
 * @private
 *
 * The function behind a method, also when a helper such as `debounce(function () {}, 400)` wraps it.
 */
export function functionOf(value: ts.Node): FunctionNode | undefined {
    if (isFunctionNode(value)) {
        return value;
    }

    const [wrapped] = ts.isCallExpression(value) ? value.arguments : [];

    return wrapped !== undefined && isFunctionNode(wrapped) ? wrapped : undefined;
}

// The methods that chain on a promise and return a new one.
const PROMISE_CHAIN = ['then', 'catch', 'finally'];

/** `load().then(...)`, `Promise.all(...)` or `new Promise(...)`: a promise the source visibly builds. */
function isVisiblePromise(expression: ts.Expression): boolean {
    const value = withoutTypeSyntax(expression);

    if (ts.isNewExpression(value)) {
        return ts.isIdentifier(value.expression) && value.expression.text === 'Promise';
    }

    if (!ts.isCallExpression(value) || !ts.isPropertyAccessExpression(value.expression)) {
        return false;
    }

    const { expression: receiver, name } = value.expression;

    return PROMISE_CHAIN.includes(name.text) || (ts.isIdentifier(receiver) && receiver.text === 'Promise');
}

function isPromiseType(type: ts.TypeNode | undefined): boolean {
    return (
        type !== undefined &&
        ts.isTypeReferenceNode(type) &&
        ts.isIdentifier(type.typeName) &&
        type.typeName.text === 'Promise'
    );
}

/** `this.$super('onSave', ...)` inside the method `onSave`. */
function isSuperCall(expression: ts.Expression, method: string): boolean {
    const value = withoutTypeSyntax(expression);

    if (!ts.isCallExpression(value) || !ts.isPropertyAccessExpression(value.expression)) {
        return false;
    }

    const [name] = value.arguments;

    return (
        value.expression.expression.kind === ts.SyntaxKind.ThisKeyword &&
        value.expression.name.text === '$super' &&
        name !== undefined &&
        ts.isStringLiteralLike(name) &&
        name.text === method
    );
}

/** The function has at least one `return`, and each of them hands back a value that `matches`. */
function everyReturn(fn: FunctionNode, matches: (value: ts.Expression) => boolean): boolean {
    const values = returnedValues(fn);

    return values.length > 0 && values.every((value) => value !== undefined && matches(value));
}

/**
 * Whether callers get a promise. A plain call such as `this.repository.save()` may return one, but only the
 * type checker knows, so the reader does not count it.
 */
function returnsPromise(fn: FunctionNode): boolean {
    const isAsync = fn.modifiers?.some(({ kind }) => kind === ts.SyntaxKind.AsyncKeyword) ?? false;

    return isAsync || isPromiseType(fn.type) || everyReturn(fn, isVisiblePromise);
}

/**
 * @private
 *
 * How the method `name` is called and what it returns. `fn` is `undefined` when the method is no function in
 * the source, for example an imported one.
 */
export function signatureOf(
    fn: FunctionNode | undefined,
    name: string,
    reading: Reading,
): Pick<Member<'method'>, 'parameters' | 'returns' | 'returnsPromise' | 'returnsSuper'> {
    return {
        parameters: fn === undefined ? null : parametersOf(fn, reading),
        returns: fn?.type === undefined ? null : reading.text(fn.type),
        returnsPromise: fn !== undefined && returnsPromise(fn),
        returnsSuper: fn !== undefined && everyReturn(fn, (returned) => isSuperCall(returned, name)),
    };
}

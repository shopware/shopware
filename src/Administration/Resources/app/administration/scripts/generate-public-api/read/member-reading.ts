/**
 * @sw-package framework
 *
 * What the readers of members share: the name, line and JSDoc every member has, the notes about what a reader
 * could not read, the entries and options of an object literal, and the signature of a function. Only the syntax is read, so code inside a function never
 * changes the result, apart from what its `return` statements hand back.
 */
import ts from 'typescript';
import { parseMarkers } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import { BOTH_STATES } from '../model/flag-states';
import type { DeclaredKind, Member, Parameter, UnitMembers } from '../model/members';
import type { Declarations, Located } from './declarations';
import {
    docOf,
    isFunctionNode,
    isPrivateDoc,
    lineOf,
    memberName,
    memberValue,
    returnedValues,
    statementOf,
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

    /** The type as the source writes it, `null` without one. */
    public typeText(type: ts.TypeNode | undefined): string | null {
        return type === undefined ? null : this.text(type);
    }
}

/** @private */
export interface Entry {
    name: string;
    member: ts.ObjectLiteralElementLike;
}

/**
 * @private
 *
 * The members of an object literal with a literal name. `spread` turns a spread into entries, for the helpers
 * whose names are known. Every other spread and every computed key is noted.
 */
export function entriesOf(
    object: ts.ObjectLiteralExpression,
    section: string,
    reading: Reading,
    spread: (member: ts.SpreadAssignment) => Entry[] | null = () => null,
): Entry[] {
    return object.properties.flatMap((member): Entry[] => {
        const name = memberName(member);

        if (name !== null) {
            return [{ name, member }];
        }

        const fromSpread = ts.isSpreadAssignment(member) ? spread(member) : null;

        if (fromSpread === null) {
            reading.unresolved(section, member);
        }

        return fromSpread ?? [];
    });
}

/**
 * @private
 *
 * The value of `{ key: value }` or `{ key }` across files, and the node that carries its comment and its line.
 * A value that the same file declares under a name, as `{ getToken }` does for a function, carries them where
 * it is declared.
 */
export function literalValue(
    member: ts.PropertyAssignment | ts.ShorthandPropertyAssignment,
    declarations: Declarations,
    reading: Reading,
): { value: Located; at: ts.Node } {
    const written = ts.isPropertyAssignment(member) ? member.initializer : member.name;
    const value = declarations.valueOf(written, reading.source);
    const isDeclaredHere =
        ts.isIdentifier(withoutTypeSyntax(written)) && value.source === reading.source && !ts.isIdentifier(value.node);

    return { value, at: isDeclaredHere ? statementOf(value.node) : member };
}

/**
 * @private
 *
 * An option of an object literal that holds the members of one kind, with the function that reads them.
 */
export interface OptionReader {
    option: string;
    kind: DeclaredKind;
    read: (value: ts.Node, reading: Reading) => Member[];
}

/**
 * @private
 *
 * The members that the options of the object hold, with the line of each option that holds some. `other` gets
 * every option that no reader takes.
 */
export function readOptions(
    object: ts.ObjectLiteralExpression,
    readers: readonly OptionReader[],
    reading: Reading,
    other: (option: ts.ObjectLiteralElementLike, name: string | null) => void = () => undefined,
): Pick<UnitMembers, 'members' | 'sections'> {
    const sections: UnitMembers['sections'] = {};
    const members = object.properties.flatMap((option) => {
        const name = memberName(option);
        const reader = readers.find((candidate) => candidate.option === name);

        if (reader === undefined) {
            other(option, name);

            return [];
        }

        sections[reader.kind] = lineOf(option, reading.source);

        return reader.read(reading.value(option), reading);
    });

    return { members, sections };
}

/** @private */
export function parametersOf(fn: FunctionNode, reading: Reading): Parameter[] {
    return fn.parameters
        .filter(({ name }) => !ts.isIdentifier(name) || name.text !== 'this')
        .map(({ name, type, initializer, questionToken, dotDotDotToken }, position) => ({
            name: ts.isIdentifier(name) ? name.text : `arg${position}`,
            pattern: ts.isIdentifier(name) ? null : reading.text(name),
            type: reading.typeText(type),
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

// The values a function can hand back that are visibly no promise.
const VALUE_EXPRESSIONS = new Set([
    ts.SyntaxKind.StringLiteral,
    ts.SyntaxKind.NumericLiteral,
    ts.SyntaxKind.BigIntLiteral,
    ts.SyntaxKind.RegularExpressionLiteral,
    ts.SyntaxKind.NoSubstitutionTemplateLiteral,
    ts.SyntaxKind.TemplateExpression,
    ts.SyntaxKind.TrueKeyword,
    ts.SyntaxKind.FalseKeyword,
    ts.SyntaxKind.NullKeyword,
    ts.SyntaxKind.ThisKeyword,
    ts.SyntaxKind.ObjectLiteralExpression,
    ts.SyntaxKind.ArrayLiteralExpression,
    ts.SyntaxKind.ArrowFunction,
    ts.SyntaxKind.FunctionExpression,
    ts.SyntaxKind.ClassExpression,
    ts.SyntaxKind.PrefixUnaryExpression,
    ts.SyntaxKind.TypeOfExpression,
    ts.SyntaxKind.VoidExpression,
    ts.SyntaxKind.DeleteExpression,
]);

// The binary operators that hand back one of their operands, which may be a promise.
const OPERAND_OPERATORS = new Set([
    ts.SyntaxKind.AmpersandAmpersandToken,
    ts.SyntaxKind.BarBarToken,
    ts.SyntaxKind.QuestionQuestionToken,
    ts.SyntaxKind.CommaToken,
    ts.SyntaxKind.EqualsToken,
    ts.SyntaxKind.AmpersandAmpersandEqualsToken,
    ts.SyntaxKind.BarBarEqualsToken,
    ts.SyntaxKind.QuestionQuestionEqualsToken,
]);

// The declared return types that are visibly no promise.
const VALUE_TYPES = new Set([
    ts.SyntaxKind.VoidKeyword,
    ts.SyntaxKind.UndefinedKeyword,
    ts.SyntaxKind.NeverKeyword,
    ts.SyntaxKind.BooleanKeyword,
    ts.SyntaxKind.StringKeyword,
    ts.SyntaxKind.NumberKeyword,
    ts.SyntaxKind.BigIntKeyword,
    ts.SyntaxKind.SymbolKeyword,
    ts.SyntaxKind.LiteralType,
    ts.SyntaxKind.ArrayType,
    ts.SyntaxKind.TupleType,
    ts.SyntaxKind.TypeLiteral,
    ts.SyntaxKind.FunctionType,
]);

/** `true` when every part is a promise, `false` when none is, which also holds for no parts, and `null` else. */
function combined(parts: (boolean | null)[]): boolean | null {
    if (parts.length > 0 && parts.every((part) => part === true)) {
        return true;
    }

    return parts.every((part) => part === false) ? false : null;
}

/**
 * Whether a returned value is a promise, `null` when only the type checker knows. `undefined` stands for a
 * `return` without a value.
 */
function promiseOf(expression: ts.Expression | undefined): boolean | null {
    if (expression === undefined) {
        return false;
    }

    const value = withoutTypeSyntax(expression);

    if (isVisiblePromise(value)) {
        return true;
    }

    if (ts.isBinaryExpression(value)) {
        return OPERAND_OPERATORS.has(value.operatorToken.kind) ? null : false;
    }

    if (ts.isIdentifier(value)) {
        return value.text === 'undefined' ? false : null;
    }

    return ts.isNewExpression(value) || VALUE_EXPRESSIONS.has(value.kind) ? false : null;
}

/** The same for a declared type. A type name such as `AxiosPromise` is `null`, as only the type checker resolves it. */
function promiseOfType(type: ts.TypeNode): boolean | null {
    if (ts.isUnionTypeNode(type)) {
        return combined(type.types.map(promiseOfType));
    }

    if (ts.isTypeReferenceNode(type) && ts.isIdentifier(type.typeName) && type.typeName.text === 'Promise') {
        return true;
    }

    return VALUE_TYPES.has(type.kind) ? false : null;
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

/** What the `return` statements of the function hand back, for a function whose declared type does not say. */
function promiseOfBody(fn: FunctionNode): boolean | null {
    if (fn.body === undefined) {
        return null;
    }

    const returned = combined(returnedValues(fn).map(promiseOf));

    // A type that only the type checker resolves can be a promise whatever the body hands back.
    return returned === false && fn.type !== undefined ? null : returned;
}

/**
 * Whether callers get a promise, `null` when only the type checker knows, as for a plain call such as
 * `return this.repository.save()`.
 */
function returnsPromise(fn: FunctionNode): boolean | null {
    if (fn.modifiers?.some(({ kind }) => kind === ts.SyntaxKind.AsyncKeyword)) {
        return true;
    }

    return (fn.type === undefined ? null : promiseOfType(fn.type)) ?? promiseOfBody(fn);
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
        returns: reading.typeText(fn?.type),
        returnsPromise: fn === undefined ? null : returnsPromise(fn),
        returnsSuper: fn !== undefined && everyReturn(fn, (returned) => isSuperCall(returned, name)),
    };
}

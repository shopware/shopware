/**
 * @sw-package framework
 *
 * Reads the members a component declares in its options object: mixins, inject, provide, props, emits, data,
 * computed properties and methods. Only the syntax is read, so code inside a method never changes the result.
 * What the syntax does not say, such as the names a spread adds, becomes a note instead of a silent gap.
 */
import ts from 'typescript';
import { byCodePoint } from '../../helpers/code-point-order';
import { parseMarkers } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { BOTH_STATES } from '../../model/flag-states';
import type { ComponentMembers, DefaultValue, Member, OptionKind, Parameter } from '../../model/members';
import {
    docOf,
    isFunctionNode,
    lineOf,
    memberName,
    memberValue,
    returnedObject,
    returnedValues,
    textOf,
    withoutTypeSyntax,
} from '../script-nodes';
import type { FunctionNode } from '../script-nodes';
import type { ComponentOptions } from './component-options';
import { spreadNames } from './option-helpers';

const PRIVATE_TAG = /^[\s*]*@private\b/m;

// Enough of an expression to find it in the source, without printing a whole function into the report.
const NOTE_LENGTH = 80;

// The types of the built-in prop constructors. Any other constructor is a class, which stands for its own type.
const PROP_TYPES: Record<string, string> = {
    String: 'string',
    Number: 'number',
    Boolean: 'boolean',
    Object: 'object',
    Array: 'any[]',
    Function: '(...args: any[]) => any',
    Date: 'Date',
    Symbol: 'symbol',
};

/** The name, line and JSDoc every member has. */
type Common = Pick<Member, 'name' | 'at' | 'markers' | 'isPrivate' | 'states'>;

class Reading {
    public readonly notes: string[] = [];

    public constructor(public readonly source: ts.SourceFile) {}

    public common(name: string, node: ts.Node): Common {
        const doc = docOf(node, this.source);

        return {
            name,
            at: lineOf(node, this.source),
            markers: parseMarkers(doc).flatMap((match): Marker[] => ('marker' in match ? [match.marker] : [])),
            isPrivate: PRIVATE_TAG.test(doc),
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

interface Entry {
    name: string;
    member: ts.ObjectLiteralElementLike;
}

/**
 * The members of an object literal with a literal name. `spread` turns a spread into entries, for the helpers
 * whose names are known. Every other spread and every computed key is noted.
 */
function entriesOf(
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

function helperEntries(member: ts.SpreadAssignment, reading: Reading): Entry[] | null {
    return spreadNames(member.expression, reading.source)?.map((name) => ({ name, member })) ?? null;
}

function literalNames(list: ts.ArrayLiteralExpression, section: string, reading: Reading): ts.StringLiteralLike[] {
    return list.elements.flatMap((element) => {
        if (ts.isStringLiteralLike(element)) {
            return [element];
        }

        reading.unresolved(section, element);

        return [];
    });
}

/** `Mixin.getByName('listing')`, the same with arguments for a mixin factory, or the plain name. */
function mixinName(mixin: ts.Expression): string | undefined {
    if (ts.isStringLiteralLike(mixin)) {
        return mixin.text;
    }

    if (!ts.isCallExpression(mixin)) {
        return undefined;
    }

    const [name] = mixin.arguments;
    const isLookup = ts.isPropertyAccessExpression(mixin.expression) && mixin.expression.name.text === 'getByName';

    if (isLookup) {
        return mixin.arguments.length === 1 && ts.isStringLiteralLike(name) ? name.text : undefined;
    }

    return mixinName(mixin.expression);
}

function mixins(value: ts.Node, reading: Reading): Member<'mixin'>[] {
    if (!ts.isArrayLiteralExpression(value)) {
        reading.unresolved('mixins', value);

        return [];
    }

    return value.elements.flatMap((element): Member<'mixin'>[] => {
        const name = mixinName(withoutTypeSyntax(element));

        if (name === undefined) {
            reading.unresolved('mixins', element);

            return [];
        }

        return [{ kind: 'mixin', ...reading.common(name, element) }];
    });
}

/** The key of `{ from: 'repositoryFactory', default: null }`, or of the plain string. */
function injectedKey(value: ts.Node, local: string): string | undefined {
    if (ts.isStringLiteralLike(value)) {
        return value.text;
    }

    if (!ts.isObjectLiteralExpression(value)) {
        return undefined;
    }

    const from = value.properties.find((property) => memberName(property) === 'from');

    if (from === undefined) {
        return local;
    }

    return ts.isPropertyAssignment(from) && ts.isStringLiteralLike(from.initializer) ? from.initializer.text : undefined;
}

function inject(value: ts.Node, reading: Reading): Member<'inject'>[] {
    if (ts.isArrayLiteralExpression(value)) {
        return literalNames(value, 'inject', reading).map((name) => ({
            kind: 'inject',
            ...reading.common(name.text, name),
            from: name.text,
        }));
    }

    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('inject', value);

        return [];
    }

    return entriesOf(value, 'inject', reading).flatMap(({ name, member }): Member<'inject'>[] => {
        const from = injectedKey(reading.value(member), name);

        if (from === undefined) {
            reading.unresolved('inject', member);

            return [];
        }

        return [{ kind: 'inject', ...reading.common(name, member), from }];
    });
}

/** The object literal a section holds directly or returns from a function, as `data()` and `provide()` do. */
function declaredObject(value: ts.Node): ts.ObjectLiteralExpression | null {
    if (ts.isObjectLiteralExpression(value)) {
        return value;
    }

    return isFunctionNode(value) ? returnedObject(value) : null;
}

function provide(value: ts.Node, reading: Reading): Member<'provide'>[] {
    const object = declaredObject(value);

    if (object === null) {
        reading.unresolved('provide', value);

        return [];
    }

    return entriesOf(object, 'provide', reading).map(({ name, member }) => ({
        kind: 'provide',
        ...reading.common(name, member),
    }));
}

/**
 * The constructors Vue checks a value against: `String`, `[String, null]` or a class such as `Criteria`. A
 * single `null` checks nothing, so the prop is untyped.
 */
function propConstructors(type: ts.Node, inList = false): string | null {
    const value = ts.isExpression(type) ? withoutTypeSyntax(type) : type;

    if (ts.isArrayLiteralExpression(value)) {
        const types = value.elements.map((element) => propConstructors(element, true));

        return types.length > 0 && types.every((name) => name !== null) ? types.sort(byCodePoint).join(' | ') : null;
    }

    if (value.kind === ts.SyntaxKind.NullKeyword) {
        return inList ? 'null' : null;
    }

    return ts.isIdentifier(value) ? (PROP_TYPES[value.text] ?? value.text) : null;
}

/** `Product` of `Object as PropType<Product>`. */
function propAnnotation(type: ts.Node, reading: Reading): string | null {
    if (!ts.isAsExpression(type)) {
        return null;
    }

    const [annotated] = ts.isTypeReferenceNode(type.type) ? (type.type.typeArguments ?? []) : [];

    return annotated === undefined ? propAnnotation(type.expression, reading) : reading.text(annotated);
}

function propTypes(type: ts.Node, reading: Reading): Pick<Member<'prop'>, 'constructors' | 'annotation'> {
    return { constructors: propConstructors(type), annotation: propAnnotation(type, reading) };
}

function optionOf(options: ts.ObjectLiteralExpression, name: string): ts.ObjectLiteralElementLike | undefined {
    return options.properties.find((property) => memberName(property) === name);
}

function propDefault(options: ts.ObjectLiteralExpression, reading: Reading): DefaultValue {
    const declared = optionOf(options, 'default');

    if (declared === undefined) {
        return { kind: 'none' };
    }

    const value = reading.value(declared);

    return isFunctionNode(value) ? { kind: 'factory' } : { kind: 'value', text: reading.text(value) };
}

function propDetails(
    value: ts.Node,
    reading: Reading,
): Pick<Member<'prop'>, 'constructors' | 'annotation' | 'required' | 'default'> {
    if (!ts.isObjectLiteralExpression(value)) {
        return { ...propTypes(value, reading), required: false, default: { kind: 'none' } };
    }

    const type = optionOf(value, 'type');
    const required = optionOf(value, 'required');

    return {
        ...(type !== undefined && ts.isPropertyAssignment(type)
            ? propTypes(type.initializer, reading)
            : { constructors: null, annotation: null }),
        required:
            required !== undefined &&
            ts.isPropertyAssignment(required) &&
            required.initializer.kind === ts.SyntaxKind.TrueKeyword,
        default: propDefault(value, reading),
    };
}

function props(value: ts.Node, reading: Reading): Member<'prop'>[] {
    if (ts.isArrayLiteralExpression(value)) {
        return literalNames(value, 'props', reading).map((name) => ({
            kind: 'prop',
            ...reading.common(name.text, name),
            constructors: null,
            annotation: null,
            required: false,
            default: { kind: 'none' },
        }));
    }

    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('props', value);

        return [];
    }

    return entriesOf(value, 'props', reading).map(({ name, member }) => ({
        kind: 'prop',
        ...reading.common(name, member),
        ...propDetails(reading.value(member), reading),
    }));
}

function parametersOf(fn: FunctionNode, reading: Reading): Parameter[] {
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

function emits(value: ts.Node, reading: Reading): Member<'emit'>[] {
    if (ts.isArrayLiteralExpression(value)) {
        return literalNames(value, 'emits', reading).map((name) => ({
            kind: 'emit',
            ...reading.common(name.text, name),
            payload: null,
        }));
    }

    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('emits', value);

        return [];
    }

    return entriesOf(value, 'emits', reading).map(({ name, member }) => {
        const validator = reading.value(member);

        return {
            kind: 'emit',
            ...reading.common(name, member),
            payload: isFunctionNode(validator) ? parametersOf(validator, reading) : null,
        };
    });
}

/** A key inside a `@private` object is private too. */
function dataMembers(
    object: ts.ObjectLiteralExpression,
    path: string,
    reading: Reading,
    insidePrivate = false,
): Member<'data'>[] {
    return entriesOf(object, 'data', reading).flatMap(({ name, member }) => {
        const value = ts.isPropertyAssignment(member) ? withoutTypeSyntax(member.initializer) : member;
        const nested = ts.isObjectLiteralExpression(value) && value.properties.length > 0 ? value : null;
        const common = reading.common(`${path}${name}`, member);
        const isPrivate = insidePrivate || common.isPrivate;

        return [
            { kind: 'data' as const, ...common, isPrivate, nested: nested !== null },
            ...(nested === null ? [] : dataMembers(nested, `${path}${name}.`, reading, isPrivate)),
        ];
    });
}

function data(value: ts.Node, reading: Reading): Member<'data'>[] {
    const object = isFunctionNode(value) ? returnedObject(value) : null;

    if (object === null) {
        reading.unresolved('data', value);

        return [];
    }

    return dataMembers(object, '', reading);
}

/** The getter of `{ get() {}, set() {} }`, or the function itself. */
function getterOf(value: ts.Node, reading: Reading): FunctionNode | undefined {
    if (isFunctionNode(value)) {
        return value;
    }

    const getter = ts.isObjectLiteralExpression(value) ? optionOf(value, 'get') : undefined;
    const getterValue = getter && reading.value(getter);

    return getterValue !== undefined && isFunctionNode(getterValue) ? getterValue : undefined;
}

function computed(value: ts.Node, reading: Reading): Member<'computed'>[] {
    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('computed', value);

        return [];
    }

    return entriesOf(value, 'computed', reading, (spread) => helperEntries(spread, reading)).map(({ name, member }) => {
        const returns = ts.isSpreadAssignment(member) ? undefined : getterOf(reading.value(member), reading)?.type;

        return {
            kind: 'computed',
            ...reading.common(name, member),
            type: returns === undefined ? null : reading.text(returns),
        };
    });
}

/** The function behind a method, also when a helper such as `debounce(function () {}, 400)` wraps it. */
function functionOf(value: ts.Node): FunctionNode | undefined {
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

function methods(value: ts.Node, reading: Reading): Member<'method'>[] {
    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('methods', value);

        return [];
    }

    return entriesOf(value, 'methods', reading, (spread) => helperEntries(spread, reading)).map(({ name, member }) => {
        const fn = ts.isSpreadAssignment(member) ? undefined : functionOf(reading.value(member));

        return {
            kind: 'method',
            ...reading.common(name, member),
            parameters: fn === undefined ? null : parametersOf(fn, reading),
            returns: fn?.type === undefined ? null : reading.text(fn.type),
            returnsPromise: fn !== undefined && returnsPromise(fn),
            returnsSuper: fn !== undefined && everyReturn(fn, (returned) => isSuperCall(returned, name)),
        };
    });
}

/** The option that declares the members of a kind, with its reader. A kind without an entry does not compile. */
const SECTIONS: { [Kind in OptionKind]: { option: string; read: (value: ts.Node, reading: Reading) => Member<Kind>[] } } = {
    mixin: { option: 'mixins', read: mixins },
    inject: { option: 'inject', read: inject },
    provide: { option: 'provide', read: provide },
    prop: { option: 'props', read: props },
    emit: { option: 'emits', read: emits },
    data: { option: 'data', read: data },
    computed: { option: 'computed', read: computed },
    method: { option: 'methods', read: methods },
};

const OPTION_KINDS = Object.keys(SECTIONS) as OptionKind[];

// What these options add to `this` is decided at runtime.
const UNREADABLE_OPTIONS = ['setup', 'extends'];

/**
 * @private
 *
 * The members the options object declares itself. The members of its mixins and of the component it extends
 * are in their own units.
 */
export function readOptionMembers({ object, source }: ComponentOptions): ComponentMembers {
    const reading = new Reading(source);
    const sections: ComponentMembers['sections'] = {};
    const members = object.properties.flatMap((option): Member[] => {
        const name = memberName(option);

        if (name === null || UNREADABLE_OPTIONS.includes(name)) {
            reading.unresolved('options', name === null ? option : (option.name ?? option));
        }

        const kind = OPTION_KINDS.find((candidate) => SECTIONS[candidate].option === name);

        if (kind === undefined) {
            return [];
        }

        sections[kind] = lineOf(option, source);

        return SECTIONS[kind].read(reading.value(option), reading);
    });

    return { members, notes: reading.notes, sections };
}

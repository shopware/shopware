/**
 * @sw-package framework
 *
 * Reads the members a component declares in its options object: mixins, inject, provide, props, emits, data,
 * computed properties and methods. Only the syntax is read, so code inside a method never changes the result.
 * What the syntax does not say, such as the names a spread adds, becomes a note instead of a silent gap.
 */
import ts from 'typescript';
import { byCodePoint } from '../../helpers/code-point-order';
import type { UnitMembers, DefaultValue, Member, OptionKind } from '../../model/members';
import { functionOf, parametersOf, Reading, signatureOf } from '../member-reading';
import { isFunctionNode, lineOf, memberName, returnedObject, withoutTypeSyntax } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';
import type { ComponentOptions } from './component-options';
import { spreadNames } from './option-helpers';

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

function methods(value: ts.Node, reading: Reading): Member<'method'>[] {
    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('methods', value);

        return [];
    }

    return entriesOf(value, 'methods', reading, (spread) => helperEntries(spread, reading)).map(({ name, member }) => {
        const fn = ts.isSpreadAssignment(member) ? undefined : functionOf(reading.value(member));

        return { kind: 'method', ...reading.common(name, member), ...signatureOf(fn, name, reading) };
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
export function readOptionMembers({ object, source }: ComponentOptions): UnitMembers {
    const reading = new Reading(source);
    const sections: UnitMembers['sections'] = {};
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

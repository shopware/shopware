/**
 * @sw-package framework
 *
 * Reads the state, the getters and the actions of a store. An options store names the three, and a setup store
 * returns them from a function, where the call that builds a value says what it is.
 */
import ts from 'typescript';
import type { Member, UnitMembers } from '../../model/members';
import type { Declarations, Located } from '../declarations';
import {
    elementValue,
    entriesOf,
    functionOf,
    heldValue,
    Reading,
    readLiteral,
    readOptions,
    signatureOf,
    spreadLiteral,
} from '../member-reading';
import type { Common, Entry, OptionReader } from '../member-reading';
import { isFunctionNode, memberName, returnedObject, returnedValues, withoutTypeSyntax } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';

type StoreMember = Member<'state'> | Member<'getter'> | Member<'action'>;

function withoutParentheses(expression: ts.Expression): ts.Expression {
    return ts.isParenthesizedExpression(expression) ? withoutParentheses(expression.expression) : expression;
}

/** `T` of `<value> as T`, with or without parentheses around it. */
function assertedType(expression: ts.Expression | undefined): ts.TypeNode | undefined {
    const value = expression && withoutParentheses(expression);

    return value !== undefined && ts.isAsExpression(value) ? value.type : undefined;
}

/** The properties of a type literal, or of an interface or type alias that the same file declares under the name. */
function typeProperties(type: ts.TypeNode | undefined, source: ts.SourceFile): ts.PropertySignature[] {
    if (type === undefined) {
        return [];
    }

    if (ts.isTypeLiteralNode(type)) {
        return type.members.filter(ts.isPropertySignature);
    }

    const name = ts.isTypeReferenceNode(type) && ts.isIdentifier(type.typeName) ? type.typeName.text : undefined;
    const declared = source.statements.find(
        (statement): statement is ts.InterfaceDeclaration | ts.TypeAliasDeclaration =>
            (ts.isInterfaceDeclaration(statement) || ts.isTypeAliasDeclaration(statement)) && statement.name.text === name,
    );

    if (declared === undefined) {
        return [];
    }

    return ts.isInterfaceDeclaration(declared)
        ? declared.members.filter(ts.isPropertySignature)
        : typeProperties(declared.type, source);
}

/**
 * The type the source writes for a key of the state: an `as` on its value, or the property of the type that
 * the state function declares to return or asserts its object to be.
 */
function stateType(
    key: ts.ObjectLiteralElementLike,
    name: string,
    declared: ts.PropertySignature[],
): ts.TypeNode | undefined {
    const asserted = ts.isPropertyAssignment(key) ? assertedType(key.initializer) : undefined;

    return asserted ?? declared.find((property) => memberName(property) === name)?.type;
}

function state(value: ts.Node, reading: Reading): StoreMember[] {
    const fn = isFunctionNode(value) ? value : undefined;
    const object = fn && returnedObject(fn);

    if (fn === undefined || !object) {
        reading.unresolved('state', value);

        return [];
    }

    const declared = typeProperties(fn.type ?? assertedType(returnedValues(fn)[0]), reading.source);

    return entriesOf(object, 'state', reading).map(({ name, member }) => ({
        kind: 'state',
        ...reading.common(name, member),
        type: reading.typeText(stateType(member, name, declared)),
    }));
}

function getter(name: string, member: Entry['member'], declarations: Declarations, reading: Reading): StoreMember {
    const { value, common } = elementValue(name, member, declarations, reading);
    const type = isFunctionNode(value.node) ? value.node.type : undefined;

    return { kind: 'getter', ...common, type: new Reading(value.source).typeText(type) };
}

function action(name: string, member: Entry['member'], declarations: Declarations, reading: Reading): StoreMember {
    const { value, common } = elementValue(name, member, declarations, reading);

    return { kind: 'action', ...common, ...signatureOf(functionOf(value.node), name, new Reading(value.source)) };
}

/** Reads an option that holds one member per entry. The option is noted when it is no object literal. */
function entryReader(
    option: string,
    kind: OptionReader['kind'],
    read: typeof getter,
    declarations: Declarations,
): OptionReader {
    return {
        option,
        kind,
        read: (value, reading) => {
            if (!ts.isObjectLiteralExpression(value)) {
                reading.unresolved(option, value);

                return [];
            }

            return entriesOf(value, option, reading).map(({ name, member }) => read(name, member, declarations, reading));
        },
    };
}

// Every other option of a store, such as `id`, holds no members.
function optionReaders(declarations: Declarations): readonly OptionReader[] {
    return [
        { option: 'state', kind: 'state', read: state },
        entryReader('getters', 'getter', getter, declarations),
        entryReader('actions', 'action', action, declarations),
    ];
}

/**
 * @private
 *
 * The members of a store that is given by its options, `{ state, getters, actions }`.
 */
export function optionsMembers(
    { node, source }: Located<ts.ObjectLiteralExpression>,
    declarations: Declarations,
): UnitMembers {
    const reading = new Reading(source);

    return { ...readOptions(node, optionReaders(declarations), reading), notes: reading.notes };
}

/** `T` of `ref<T>(...)`, or what the function inside `computed(...)` declares to return. */
function builtType(call: ts.CallExpression): ts.TypeNode | undefined {
    const [argument] = call.arguments;
    const inner = argument && withoutTypeSyntax(argument);

    return call.typeArguments?.[0] ?? (inner !== undefined && isFunctionNode(inner) ? inner.type : undefined);
}

const BUILT_KINDS = { computed: 'getter', ref: 'state' } as const;

/** A value that a setup store returns: a getter, an action, or state for everything else. */
function setupMember(common: Common, { node, source }: Located): StoreMember {
    const reading = new Reading(source);
    const held = heldValue(node);

    switch (held.is) {
        case 'computed':
        case 'ref':
            return { kind: BUILT_KINDS[held.is], ...common, type: reading.typeText(builtType(held.built)) };
        case 'function':
            return { kind: 'action', ...common, ...signatureOf(held.fn, common.name, reading) };
        case 'value':
            return { kind: 'state', ...common, type: null };
        default:
            return held satisfies never;
    }
}

/** A key of the object a setup store returns, `null` for a setter, which holds no value. */
function returnedMember(
    member: ts.ObjectLiteralElementLike,
    name: string,
    declarations: Declarations,
    reading: Reading,
): StoreMember | null {
    if (ts.isGetAccessorDeclaration(member)) {
        return { kind: 'getter', ...reading.common(name, member), type: reading.typeText(member.type) };
    }

    if (ts.isSetAccessorDeclaration(member)) {
        return null;
    }

    const { value, common } = elementValue(name, member, declarations, reading);

    return setupMember(common, value);
}

/**
 * The keys of an object literal that a setup store spreads into what it returns, as `...state` or
 * `...toRefs(state)` for `const state = reactive({ ... })`.
 */
function spreadState(spread: ts.SpreadAssignment, declarations: Declarations, reading: Reading): StoreMember[] | null {
    const literal = spreadLiteral(spread, declarations, reading);

    if (literal === null) {
        return null;
    }

    const spreadReading = new Reading(literal.source);
    const members = entriesOf(literal.node, 'members', spreadReading).map(
        ({ name, member }): StoreMember => ({ kind: 'state', ...spreadReading.common(name, member), type: null }),
    );

    reading.notes.push(...spreadReading.notes);

    return members;
}

/**
 * @private
 *
 * The members of a store that a function sets up: what the object it returns holds. Everything else in that
 * object is noted.
 */
export function setupMembers({ node, source }: Located<FunctionNode>, declarations: Declarations): UnitMembers {
    const reading = new Reading(source);
    const returned = returnedObject(node);

    if (returned === null) {
        reading.unresolved('store', node);

        return { members: [], notes: reading.notes, sections: {} };
    }

    const members = readLiteral(
        returned,
        'members',
        reading,
        (member, name) => {
            const found = returnedMember(member, name, declarations, reading);

            return found && [found];
        },
        (spread) => spreadState(spread, declarations, reading),
    );

    return { members, notes: reading.notes, sections: {} };
}

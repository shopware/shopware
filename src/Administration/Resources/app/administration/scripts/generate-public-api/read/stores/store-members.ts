/**
 * @sw-package framework
 *
 * Reads the state, the getters and the actions of a store. An options store names the three, and a setup store
 * returns them from a function, where the call that builds a value says what it is.
 */
import ts from 'typescript';
import type { Member, UnitMembers } from '../../model/members';
import type { Declarations, Located } from '../declarations';
import { entriesOf, functionOf, literalValue, Reading, readOptions, signatureOf } from '../member-reading';
import type { OptionReader } from '../member-reading';
import { callOf, isFunctionNode, memberName, returnedObject, returnedValues, withoutTypeSyntax } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';

type StoreMember = Member<'state'> | Member<'getter'> | Member<'action'>;

/**
 * @private
 *
 * The members of a store with what could not be read of it, and for an options store the lines of its options.
 */
export type StoreMembers = Pick<UnitMembers, 'members' | 'notes'> & Partial<Pick<UnitMembers, 'sections'>>;

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

function getters(value: ts.Node, reading: Reading): StoreMember[] {
    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('getters', value);

        return [];
    }

    return entriesOf(value, 'getters', reading).map(({ name, member }) => {
        const getter = reading.value(member);

        return {
            kind: 'getter',
            ...reading.common(name, member),
            type: reading.typeText(isFunctionNode(getter) ? getter.type : undefined),
        };
    });
}

function actions(value: ts.Node, reading: Reading): StoreMember[] {
    if (!ts.isObjectLiteralExpression(value)) {
        reading.unresolved('actions', value);

        return [];
    }

    return entriesOf(value, 'actions', reading).map(({ name, member }) => ({
        kind: 'action',
        ...reading.common(name, member),
        ...signatureOf(functionOf(reading.value(member)), name, reading),
    }));
}

// Every other option of a store, such as `id`, holds no members.
const OPTION_READERS: readonly OptionReader[] = [
    { option: 'state', kind: 'state', read: state },
    { option: 'getters', kind: 'getter', read: getters },
    { option: 'actions', kind: 'action', read: actions },
];

/**
 * @private
 *
 * The members of a store that is given by its options, `{ state, getters, actions }`.
 */
export function optionsMembers({ node, source }: Located<ts.ObjectLiteralExpression>): StoreMembers {
    const reading = new Reading(source);

    return { ...readOptions(node, OPTION_READERS, reading), notes: reading.notes };
}

// What these calls of Vue return is state, and what `computed` returns is a getter.
const STATE_CALLS = [
    'ref',
    'shallowRef',
    'reactive',
    'shallowReactive',
    'readonly',
];

const GETTER_CALLS = ['computed'];

/** `T` of `ref<T>(...)`, or what the function inside `computed(...)` declares to return. */
function builtType(call: ts.CallExpression): ts.TypeNode | undefined {
    const [argument] = call.arguments;
    const inner = argument && withoutTypeSyntax(argument);

    return call.typeArguments?.[0] ?? (inner !== undefined && isFunctionNode(inner) ? inner.type : undefined);
}

/** A value that a setup store returns: a getter, an action, or state for everything else. `at` carries its comment. */
function setupMember(name: string, at: ts.Node, { node, source }: Located, reading: Reading): StoreMember {
    const typeReading = new Reading(source);
    const getter = callOf(node, GETTER_CALLS);
    const built = getter ?? callOf(node, STATE_CALLS);

    if (built !== undefined) {
        return {
            kind: getter === undefined ? 'state' : 'getter',
            ...reading.common(name, at),
            type: typeReading.typeText(builtType(built)),
        };
    }

    const fn = functionOf(node);

    return fn === undefined
        ? { kind: 'state', ...reading.common(name, at), type: null }
        : { kind: 'action', ...reading.common(name, at), ...signatureOf(fn, name, typeReading) };
}

/** A key of the object a setup store returns, `null` for one that holds no value the reader knows. */
function returnedMember(
    member: ts.ObjectLiteralElementLike,
    name: string,
    declarations: Declarations,
    reading: Reading,
): StoreMember | null {
    if (ts.isMethodDeclaration(member)) {
        return { kind: 'action', ...reading.common(name, member), ...signatureOf(member, name, reading) };
    }

    if (ts.isGetAccessorDeclaration(member)) {
        return { kind: 'getter', ...reading.common(name, member), type: reading.typeText(member.type) };
    }

    if (!ts.isPropertyAssignment(member) && !ts.isShorthandPropertyAssignment(member)) {
        return null;
    }

    const { value, at } = literalValue(member, declarations, reading);

    return setupMember(name, at, value, reading);
}

/**
 * The keys of a reactive object that a setup store spreads into what it returns, as `...state` or
 * `...toRefs(state)` for `const state = reactive({ ... })`. `null` for a spread of anything else.
 */
function spreadState(spread: ts.SpreadAssignment, declarations: Declarations, reading: Reading): StoreMember[] | null {
    const written = withoutTypeSyntax(spread.expression);
    const [wrapped] = callOf(written, ['toRefs'])?.arguments ?? [];
    const { node, source } = declarations.valueOf(wrapped ?? written, reading.source);
    const [object] = callOf(node, STATE_CALLS)?.arguments ?? [];
    const literal = object && withoutTypeSyntax(object);

    if (literal === undefined || !ts.isObjectLiteralExpression(literal) || source !== reading.source) {
        return null;
    }

    return entriesOf(literal, 'members', reading).map(({ name, member }) => ({
        kind: 'state',
        ...reading.common(name, member),
        type: null,
    }));
}

/**
 * @private
 *
 * The members of a store that a function sets up: what the object it returns holds. Everything else in that
 * object is noted.
 */
export function setupMembers({ node, source }: Located<FunctionNode>, declarations: Declarations): StoreMembers {
    const reading = new Reading(source);
    const returned = returnedObject(node);

    if (returned === null) {
        reading.unresolved('store', node);

        return { members: [], notes: reading.notes };
    }

    const members = returned.properties.flatMap((member): StoreMember[] => {
        const name = memberName(member);
        const read = (): StoreMember[] | null => {
            const found = name === null ? null : returnedMember(member, name, declarations, reading);

            return found && [found];
        };
        const found = ts.isSpreadAssignment(member) ? spreadState(member, declarations, reading) : read();

        if (found === null) {
            reading.unresolved('members', member);
        }

        return found ?? [];
    });

    return { members, notes: reading.notes };
}

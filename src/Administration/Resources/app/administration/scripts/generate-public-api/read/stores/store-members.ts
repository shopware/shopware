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
import type { Common, OptionReader } from '../member-reading';
import { callOf, isFunctionNode, memberName, returnedObject, returnedValues, withoutTypeSyntax } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';
import { GETTER_CALLS, STATE_CALLS } from '../vue-values';

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

// What `STATE_CALLS` return is state, and what `GETTER_CALLS` return is a getter. A reactive object is followed
// to the object it wraps, which is state like every other value.

/** `T` of `ref<T>(...)`, or what the function inside `computed(...)` declares to return. */
function builtType(call: ts.CallExpression): ts.TypeNode | undefined {
    const [argument] = call.arguments;
    const inner = argument && withoutTypeSyntax(argument);

    return call.typeArguments?.[0] ?? (inner !== undefined && isFunctionNode(inner) ? inner.type : undefined);
}

/** A value that a setup store returns: a getter, an action, or state for everything else. */
function setupMember(common: Common, { node, source }: Located): StoreMember {
    const reading = new Reading(source);
    const getter = callOf(node, GETTER_CALLS);
    const built = getter ?? callOf(node, STATE_CALLS);

    if (built !== undefined) {
        return { kind: getter === undefined ? 'state' : 'getter', ...common, type: reading.typeText(builtType(built)) };
    }

    const fn = functionOf(node);

    return fn === undefined
        ? { kind: 'state', ...common, type: null }
        : { kind: 'action', ...common, ...signatureOf(fn, common.name, reading) };
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

    const { value, common } = literalValue(name, member, declarations, reading);

    return setupMember(common, value);
}

/**
 * The keys of an object that a setup store spreads into what it returns, as `...state` or `...toRefs(state)`
 * for `const state = reactive({ ... })`. `null` for a spread of anything but an object literal of the same file.
 */
function spreadState(spread: ts.SpreadAssignment, declarations: Declarations, reading: Reading): StoreMember[] | null {
    const { node, source } = declarations.valueOf(spread.expression, reading.source);

    if (!ts.isObjectLiteralExpression(node) || source !== reading.source) {
        return null;
    }

    return entriesOf(node, 'members', reading).map(({ name, member }) => ({
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

/**
 * @sw-package framework
 *
 * Reads the members of a plain object: an instance of a class, an object literal, or a class with its static
 * members. These are the properties and methods that code holding the object can reach, so `private`,
 * `protected` and `#private` members of a class are left out.
 */
import ts from 'typescript';
import type { Member } from '../model/members';
import type { Declarations, Located } from './declarations';
import { elementValue, heldValue, namedValue, Reading, readLiteral, signatureOf, spreadLiteral } from './member-reading';
import type { Common } from './member-reading';
import { modifiersOf, namedClassElements, statementOf, withoutTypeSyntax } from './script-nodes';
import type { FunctionNode, NamedClassElement } from './script-nodes';

type ObjectMember = Member<'method'> | Member<'property'>;

/**
 * @private
 *
 * The members of an object with what could not be read of it.
 */
export interface ObjectMembers {
    members: ObjectMember[];
    notes: string[];
}

/** `signature` reads the function, which may stand in another file than the member that names it. */
function method(common: Common, fn: FunctionNode, signature: Reading): ObjectMember {
    return { kind: 'method', ...common, ...signatureOf(fn, common.name, signature) };
}

function property(common: Common, type: ts.TypeNode | undefined, reading: Reading): ObjectMember {
    return { kind: 'property', ...common, type: reading.typeText(type) };
}

/** A method or an accessor, which a class and an object literal declare alike. A getter is a property. */
function declaredMember(element: ts.Node, name: string, reading: Reading): ObjectMember | undefined {
    if (ts.isMethodDeclaration(element)) {
        return method(reading.common(name, element), element, reading);
    }

    if (ts.isGetAccessorDeclaration(element)) {
        return property(reading.common(name, element), element.type, reading);
    }

    return ts.isSetAccessorDeclaration(element)
        ? property(reading.common(name, element), element.parameters[0]?.type, reading)
        : undefined;
}

/** A member that holds a value: a function is a method, also behind a helper, and every other value a property. */
function valueMember(common: Common, value: Located, type: ts.TypeNode | undefined, reading: Reading): ObjectMember {
    const held = heldValue(value.node);

    return held.is === 'function' ? method(common, held.fn, new Reading(value.source)) : property(common, type, reading);
}

/** A member of a name that occurs twice is the first one, or with `last` the one that replaces the others. */
function uniqueByName(members: ObjectMember[], keep: 'first' | 'last' = 'first'): ObjectMember[] {
    const seen = new Set<string>();
    const ordered = keep === 'first' ? members : [...members].reverse();
    const unique = ordered.filter(({ name }) => {
        const isNew = !seen.has(name);

        seen.add(name);

        return isNew;
    });

    return keep === 'first' ? unique : unique.reverse();
}

/**
 * @private
 *
 * `this.<name> = <value>` in the body of a constructor.
 */
export interface ThisAssignment {
    name: string;
    value: ts.Expression;
    statement: ts.Node;
}

/**
 * @private
 *
 * What the constructor of the class assigns to `this`. A function inside it that binds its own `this` is not
 * read.
 */
export function thisAssignments(declaration: ts.ClassLikeDeclaration): ThisAssignment[] {
    const body = declaration.members.find(ts.isConstructorDeclaration)?.body;
    const found: ThisAssignment[] = [];
    const visit = (node: ts.Node): void => {
        if (
            ts.isBinaryExpression(node) &&
            node.operatorToken.kind === ts.SyntaxKind.EqualsToken &&
            ts.isPropertyAccessExpression(node.left) &&
            node.left.expression.kind === ts.SyntaxKind.ThisKeyword &&
            ts.isIdentifier(node.left.name)
        ) {
            found.push({ name: node.left.name.text, value: withoutTypeSyntax(node.right), statement: statementOf(node) });
        }

        if (!ts.isFunctionLike(node) || ts.isArrowFunction(node)) {
            ts.forEachChild(node, visit);
        }
    };

    if (body !== undefined) {
        ts.forEachChild(body, visit);
    }

    return found;
}

/** What a class element or a constructor parameter declares, `undefined` for one that declares no member. */
function classMember(
    { element, name }: NamedClassElement,
    declarations: Declarations,
    reading: Reading,
): ObjectMember | undefined {
    if (ts.isParameter(element)) {
        // `constructor(public readonly id: string)` declares a property, a plain parameter none.
        return modifiersOf(element).length > 0 ? property(reading.common(name, element), element.type, reading) : undefined;
    }

    if (!ts.isPropertyDeclaration(element)) {
        return declaredMember(element, name, reading);
    }

    if (element.initializer === undefined) {
        return property(reading.common(name, element), element.type, reading);
    }

    const { value, common } = namedValue(name, element, element.initializer, declarations, reading);

    return valueMember(common, value, element.type, reading);
}

/** The members that the elements declare, in their order. */
function declaredMembers(elements: NamedClassElement[], declarations: Declarations, reading: Reading): ObjectMember[] {
    return elements.map((element) => classMember(element, declarations, reading)).filter((member) => member !== undefined);
}

/** A declared member comes before what the constructor assigns to `this` under the same name. */
function ownMembers(
    { node, source }: Located<ts.ClassLikeDeclaration>,
    declarations: Declarations,
    reading: Reading,
): ObjectMember[] {
    const elements = namedClassElements(node);
    const declaredNames = new Set(elements.filter(({ isHidden, isStatic }) => isHidden || isStatic).map(({ name }) => name));

    return uniqueByName([
        ...declaredMembers(
            elements.filter(({ isHidden, isStatic }) => !isHidden && !isStatic),
            declarations,
            reading,
        ),
        ...thisAssignments(node)
            .filter(({ name }) => !declaredNames.has(name))
            .map(({ name, value, statement }) =>
                valueMember(reading.common(name, statement), { node: value, source }, undefined, reading),
            ),
    ]);
}

/**
 * @private
 *
 * The static members of the class that code outside of it can reach, which plugin code uses on the class itself.
 */
export function staticMembers(declaration: Located<ts.ClassLikeDeclaration>, declarations: Declarations): ObjectMembers {
    const reading = new Reading(declaration.source);
    const elements = namedClassElements(declaration.node).filter(({ isHidden, isStatic }) => isStatic && !isHidden);

    return { members: uniqueByName(declaredMembers(elements, declarations, reading)), notes: reading.notes };
}

function parentOf({ node }: Located<ts.ClassLikeDeclaration>): ts.Expression | undefined {
    return node.heritageClauses?.find(({ token }) => token === ts.SyntaxKind.ExtendsKeyword)?.types[0]?.expression;
}

/**
 * @private
 *
 * A class with the members an instance of it has.
 */
export interface ClassMembers extends ObjectMembers {
    declaration: Located<ts.ClassLikeDeclaration>;
}

/**
 * @private
 *
 * The class and the classes along its `extends` chain, nearest first. Each comes with the members an instance
 * of it has, its own and those it inherits, and an own member comes before an inherited one of its name. A
 * parent that is no class of the source tree is noted and ends the chain.
 */
export function classChain(
    declaration: Located<ts.ClassLikeDeclaration>,
    declarations: Declarations,
    seen: readonly ts.Node[] = [],
): [ClassMembers, ...ClassMembers[]] {
    const reading = new Reading(declaration.source);
    const own = ownMembers(declaration, declarations, reading);
    const parent = parentOf(declaration);
    const parentClass = parent && declarations.valueOf(parent, declaration.source);

    if (parent === undefined || parentClass === undefined) {
        return [{ declaration, members: own, notes: reading.notes }];
    }

    if (!ts.isClassLike(parentClass.node) || seen.includes(parentClass.node)) {
        reading.unresolved('parent', parent);

        return [{ declaration, members: own, notes: reading.notes }];
    }

    const inherited = classChain({ node: parentClass.node, source: parentClass.source }, declarations, [
        ...seen,
        declaration.node,
    ]);
    const [nearest] = inherited;

    return [
        {
            declaration,
            members: uniqueByName([...own, ...nearest.members]),
            notes: [...reading.notes, ...nearest.notes],
        },
        ...inherited,
    ];
}

/**
 * @private
 *
 * The members an instance of the class has, its own and those it inherits.
 */
export function instanceMembers(declaration: Located<ts.ClassLikeDeclaration>, declarations: Declarations): ObjectMembers {
    const [{ members, notes }] = classChain(declaration, declarations);

    return { members, notes };
}

/** The member of an object literal under a literal name. */
function literalMember(
    element: ts.ObjectLiteralElementLike,
    name: string,
    declarations: Declarations,
    reading: Reading,
): ObjectMember {
    const declared = declaredMember(element, name, reading);

    if (declared !== undefined) {
        return declared;
    }

    const { value, common } = elementValue(name, element, declarations, reading);

    return valueMember(common, value, undefined, reading);
}

/** The members of an object literal that another one spreads, `null` when it spreads something else. */
function spreadMembers(spread: ts.SpreadAssignment, declarations: Declarations, reading: Reading): ObjectMember[] | null {
    const literal = spreadLiteral(spread, declarations, reading);

    if (literal === null) {
        return null;
    }

    const { members, notes } = objectMembers(literal, declarations);

    reading.notes.push(...notes);

    return members;
}

/**
 * @private
 *
 * The members of the object literal, of which a later one replaces an earlier one of its name. The members of
 * an object it spreads are its own, when that object is a literal too. Every other spread and every computed
 * key is noted.
 */
export function objectMembers(
    { node, source }: Located<ts.ObjectLiteralExpression>,
    declarations: Declarations,
): ObjectMembers {
    const reading = new Reading(source);
    const members = readLiteral(
        node,
        'members',
        reading,
        (element, name) => [literalMember(element, name, declarations, reading)],
        (spread) => spreadMembers(spread, declarations, reading),
    );

    return { members: uniqueByName(members, 'last'), notes: reading.notes };
}

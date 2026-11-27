/**
 * @sw-package framework
 *
 * Reads the members of a plain object: an instance of a class, or an object literal. These are the properties
 * and methods that code holding the object can reach, so `private`, `protected`, `#private` and static members
 * of a class are left out.
 */
import ts from 'typescript';
import type { Member } from '../../model/members';
import type { Declarations, Located } from '../declarations';
import { functionOf, Reading, signatureOf } from '../member-reading';
import { memberName, statementOf, withoutTypeSyntax } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';

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
function method(name: string, node: ts.Node, fn: FunctionNode, reading: Reading, signature = reading): ObjectMember {
    return { kind: 'method', ...reading.common(name, node), ...signatureOf(fn, name, signature) };
}

function property(name: string, node: ts.Node, type: ts.TypeNode | undefined, reading: Reading): ObjectMember {
    return { kind: 'property', ...reading.common(name, node), type: type === undefined ? null : reading.text(type) };
}

/** A method or an accessor, which a class and an object literal declare alike. A getter is a property. */
function declaredMember(element: ts.Node, name: string, reading: Reading): ObjectMember | undefined {
    if (ts.isMethodDeclaration(element)) {
        return method(name, element, element, reading);
    }

    if (ts.isGetAccessorDeclaration(element)) {
        return property(name, element, element.type, reading);
    }

    return ts.isSetAccessorDeclaration(element) ? property(name, element, element.parameters[0]?.type, reading) : undefined;
}

/** A member that holds a value: a function is a method, also behind a helper, and every other value a property. */
function valueMember(
    name: string,
    node: ts.Node,
    value: Located,
    type: ts.TypeNode | undefined,
    reading: Reading,
): ObjectMember {
    const fn = functionOf(value.node);

    return fn === undefined
        ? property(name, node, type, reading)
        : method(name, node, fn, reading, new Reading(value.source));
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

const HIDDEN_MODIFIERS = [
    ts.SyntaxKind.PrivateKeyword,
    ts.SyntaxKind.ProtectedKeyword,
    ts.SyntaxKind.StaticKeyword,
];

function modifiersOf(node: ts.Node): readonly ts.Modifier[] {
    return ts.canHaveModifiers(node) ? (ts.getModifiers(node) ?? []) : [];
}

/** A class element or a constructor parameter under its name. A `#private` or computed name gives none. */
interface NamedElement {
    element: ts.ClassElement | ts.ParameterDeclaration;
    name: string;
    isHidden: boolean;
}

function namedElements(declaration: ts.ClassLikeDeclaration): NamedElement[] {
    const parameters = declaration.members.find(ts.isConstructorDeclaration)?.parameters ?? [];

    return [...declaration.members, ...parameters].flatMap((element): NamedElement[] => {
        const { name } = element;
        const isNamed = name !== undefined && (ts.isIdentifier(name) || ts.isStringLiteralLike(name));

        return isNamed
            ? [
                  {
                      element,
                      name: name.text,
                      isHidden: modifiersOf(element).some(({ kind }) => HIDDEN_MODIFIERS.includes(kind)),
                  },
              ]
            : [];
    });
}

/** What a class element or a constructor parameter declares, `undefined` for one that declares no member. */
function classMember({ element, name }: NamedElement, source: ts.SourceFile, reading: Reading): ObjectMember | undefined {
    if (ts.isParameter(element)) {
        // `constructor(public readonly id: string)` declares a property, a plain parameter none.
        return modifiersOf(element).length > 0 ? property(name, element, element.type, reading) : undefined;
    }

    if (!ts.isPropertyDeclaration(element)) {
        return declaredMember(element, name, reading);
    }

    const value = element.initializer === undefined ? element : withoutTypeSyntax(element.initializer);

    return valueMember(name, element, { node: value, source }, element.type, reading);
}

/** A declared member comes before what the constructor assigns to `this` under the same name. */
function ownMembers({ node, source }: Located<ts.ClassLikeDeclaration>, reading: Reading): ObjectMember[] {
    const elements = namedElements(node);
    const hidden = new Set(elements.filter(({ isHidden }) => isHidden).map(({ name }) => name));

    return uniqueByName([
        ...elements
            .filter(({ isHidden }) => !isHidden)
            .map((element) => classMember(element, source, reading))
            .filter((member) => member !== undefined),
        ...thisAssignments(node)
            .filter(({ name }) => !hidden.has(name))
            .map(({ name, value, statement }) => valueMember(name, statement, { node: value, source }, undefined, reading)),
    ]);
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
    const own = ownMembers(declaration, reading);
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
 * The member of an object literal under a literal name. One that names a function of the same file, as
 * `{ getToken }` does, carries its comment where that function is declared.
 */
function literalMember(
    element: ts.ObjectLiteralElementLike,
    name: string,
    declarations: Declarations,
    reading: Reading,
): ObjectMember {
    const declared = declaredMember(element, name, reading);

    if (declared !== undefined || (!ts.isPropertyAssignment(element) && !ts.isShorthandPropertyAssignment(element))) {
        return declared ?? property(name, element, undefined, reading);
    }

    const value = declarations.valueOf(
        ts.isPropertyAssignment(element) ? element.initializer : element.name,
        reading.source,
    );
    const isOwnFunction = ts.isFunctionDeclaration(value.node) && value.source === reading.source;

    return valueMember(name, isOwnFunction ? value.node : element, value, undefined, reading);
}

/** The members of an object literal that another one spreads, `null` when it spreads something else. */
function spreadMembers(spread: ts.SpreadAssignment, declarations: Declarations, reading: Reading): ObjectMember[] | null {
    const { node, source } = declarations.valueOf(spread.expression, reading.source);

    if (!ts.isObjectLiteralExpression(node)) {
        return null;
    }

    const { members, notes } = objectMembers({ node, source }, declarations);

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
    const members = node.properties.flatMap((element) => {
        const name = memberName(element);
        const read = (): ObjectMember[] | null =>
            name === null ? null : [literalMember(element, name, declarations, reading)];
        const found = ts.isSpreadAssignment(element) ? spreadMembers(element, declarations, reading) : read();

        if (found === null) {
            reading.unresolved('members', element);
        }

        return found ?? [];
    });

    return { members: uniqueByName(members, 'last'), notes: reading.notes };
}

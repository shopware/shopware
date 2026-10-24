/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import * as ts from 'typescript';
import type { AnalysisContext } from './analysis-context';
import { compareText, normalizePrintedType, printType } from './type-printer';

type DigestContext = Pick<AnalysisContext, 'checker' | 'normalizeTypeText'>;

/** Compiler internals without a public counterpart; present in every TypeScript 5 release. */
const internals = ts as unknown as {
    getCheckFlags(symbol: ts.Symbol): number;
    getDeclarationModifierFlagsFromSymbol(symbol: ts.Symbol): ts.ModifierFlags;
    signatureHasRestParameter(signature: ts.Signature): boolean;
    pseudoBigIntToString(value: ts.PseudoBigInt): string;
    isIdentifierText(name: string, languageVersion: ts.ScriptTarget): boolean;
    CheckFlags: { Readonly: number };
};

type Kind = 'atom' | 'function' | 'union' | 'intersection' | 'operator' | 'loose';
type Position = 'plain' | 'union' | 'intersection' | 'array';

interface Shape {
    kind: Kind;
    /** Identity the rendering does not show, e.g. enum member values. */
    key: string;
    /** One entry more than `children`; the rendering interleaves both. */
    texts: string[];
    children: ts.Type[];
    positions: Position[];
    /** Union and intersection members, whose order carries no meaning. */
    unordered: boolean;
}

interface Digest {
    hash: string;
    length: number;
}

export interface TypeDigest extends Digest {
    /** Canonical rendering, cut after `budget` characters. */
    display(budget: number): string;
}

const INTRINSIC_FLAGS =
    ts.TypeFlags.Any |
    ts.TypeFlags.Unknown |
    ts.TypeFlags.String |
    ts.TypeFlags.Number |
    ts.TypeFlags.BigInt |
    ts.TypeFlags.Boolean |
    ts.TypeFlags.BooleanLiteral |
    ts.TypeFlags.ESSymbol |
    ts.TypeFlags.Void |
    ts.TypeFlags.Undefined |
    ts.TypeFlags.Null |
    ts.TypeFlags.Never |
    ts.TypeFlags.NonPrimitive;
const BACK_REFERENCE = '<cycle>';
const HEAD_DEPTH = 2;
const HEAD_LENGTH = 80;
const PARENTHESIZED: Record<Position, ReadonlySet<Kind>> = {
    plain: new Set(),
    union: new Set([
        'function',
        'union',
        'intersection',
        'loose',
    ]),
    intersection: new Set([
        'function',
        'union',
        'intersection',
        'loose',
    ]),
    array: new Set([
        'function',
        'union',
        'intersection',
        'operator',
        'loose',
    ]),
};

function sha1(text: string): string {
    return createHash('sha1').update(text).digest('hex');
}

function needsParentheses(kind: Kind, position: Position): boolean {
    return PARENTHESIZED[position].has(kind);
}

function isGeneric(type: ts.Type): boolean {
    if (type.flags & ts.TypeFlags.Instantiable) {
        return true;
    }

    return !!(type.flags & ts.TypeFlags.UnionOrIntersection) && (type as ts.UnionOrIntersectionType).types.some(isGeneric);
}

class ShapeBuilder {
    private readonly texts = [''];

    private readonly children: ts.Type[] = [];

    private readonly positions: Position[] = [];

    key = '';

    text(text: string): this {
        this.texts[this.texts.length - 1] += text;

        return this;
    }

    child(type: ts.Type, position: Position = 'plain'): this {
        this.children.push(type);
        this.positions.push(position);
        this.texts.push('');

        return this;
    }

    list<T>(items: readonly T[], separator: string, add: (item: T, index: number) => void): this {
        items.forEach((item, index) => {
            if (index > 0) {
                this.text(separator);
            }

            add(item, index);
        });

        return this;
    }

    build(kind: Kind, unordered = false): Shape {
        return {
            kind,
            key: this.key,
            texts: this.texts,
            children: this.children,
            positions: this.positions,
            unordered,
        };
    }
}

/**
 * Hashes, measures and renders types by walking the type graph once per distinct type, so shared parts of
 * expanded types cost nothing after their first visit. Members of reference cycles are identified by their
 * bisimulation class, which is computed by partition refinement and does not depend on type ids.
 */
class TypeDigester {
    private readonly shapes = new Map<ts.Type, Shape>();

    private readonly digests = new Map<ts.Type, Digest>();

    /** Bisimulation class of each member of a reference cycle */
    private readonly cycles = new Map<ts.Type, string>();

    private readonly heads = Array.from({ length: HEAD_DEPTH + 1 }, () => new Map<ts.Type, string>());

    constructor(private readonly context: DigestContext) {}

    digest(type: ts.Type): TypeDigest {
        if (!this.isDone(type)) {
            this.collect(type);
        }

        return { ...this.digestOf(type), display: (budget) => this.render(type, budget) };
    }

    private isDone(type: ts.Type): boolean {
        return this.digests.has(type);
    }

    private digestOf(type: ts.Type): Digest {
        const digest = this.digests.get(type);

        if (!digest) {
            throw new Error('Type was not collected');
        }

        return digest;
    }

    /** A hash that orders siblings canonically; inside a cycle the bisimulation class serves. */
    private structuralHash(type: ts.Type): string {
        return this.cycles.get(type) ?? this.digestOf(type).hash;
    }

    private signature(shape: Shape, idOf: (child: ts.Type) => string): string {
        const ids = shape.children.map(idOf);

        if (shape.unordered) {
            ids.sort(compareText);
        }

        return [
            shape.kind,
            shape.key,
            shape.texts.join('\u0001'),
            ...ids,
        ].join('\u0000');
    }

    private childLength(shape: Shape, index: number, length: number): number {
        const kind = this.shapeOf(shape.children[index]).kind;

        return length + (needsParentheses(kind, shape.positions[index]) ? 2 : 0);
    }

    private textLength(shape: Shape): number {
        return shape.texts.reduce((sum, text) => sum + text.length, 0);
    }

    /** Tarjan's algorithm, iterative because expanded types nest deeper than the call stack allows. */
    private collect(root: ts.Type): void {
        const indexes = new Map<ts.Type, number>();
        const lowLinks = new Map<ts.Type, number>();
        const stack: ts.Type[] = [];
        const onStack = new Set<ts.Type>();
        const frames: Array<{ type: ts.Type; next: number }> = [];

        const enter = (type: ts.Type): void => {
            indexes.set(type, indexes.size);
            lowLinks.set(type, indexes.size - 1);
            stack.push(type);
            onStack.add(type);
            frames.push({ type, next: 0 });
        };

        enter(root);

        while (frames.length > 0) {
            const frame = frames[frames.length - 1];
            const { children } = this.shapeOf(frame.type);

            if (frame.next < children.length) {
                const child = children[frame.next];
                frame.next += 1;

                if (this.isDone(child)) {
                    continue;
                }

                if (!indexes.has(child)) {
                    enter(child);
                } else if (onStack.has(child)) {
                    lowLinks.set(frame.type, Math.min(lowLinks.get(frame.type)!, indexes.get(child)!));
                }

                continue;
            }

            frames.pop();

            const parent = frames[frames.length - 1];

            if (parent) {
                lowLinks.set(parent.type, Math.min(lowLinks.get(parent.type)!, lowLinks.get(frame.type)!));
            }

            if (lowLinks.get(frame.type) !== indexes.get(frame.type)) {
                continue;
            }

            const component: ts.Type[] = [];
            let member: ts.Type;

            do {
                member = stack.pop()!;
                onStack.delete(member);
                component.push(member);
            } while (member !== frame.type);

            if (component.length === 1 && !this.shapeOf(frame.type).children.includes(frame.type)) {
                this.digestAcyclic(frame.type);
            } else {
                this.classifyCycle(component);
            }
        }
    }

    private digestAcyclic(type: ts.Type): void {
        const shape = this.shapeOf(type);
        const childDigests = shape.children.map((child) => this.digestOf(child));

        this.digests.set(type, {
            hash: sha1(this.signature(shape, (child) => this.digestOf(child).hash)),
            length: childDigests.reduce(
                (sum, digest, index) => this.childLength(shape, index, sum + digest.length),
                this.textLength(shape),
            ),
        });
    }

    /** Moore's partition refinement: stops as soon as a round splits no class. */
    private classifyCycle(component: ts.Type[]): void {
        const inside = new Set(component);
        const outsideId = (child: ts.Type): string => this.digestOf(child).hash;
        let classes = new Map(
            component.map((type) => [
                type,
                sha1(this.signature(this.shapeOf(type), (child) => (inside.has(child) ? '' : outsideId(child)))),
            ]),
        );
        let count = new Set(classes.values()).size;

        for (;;) {
            const previous = classes;

            classes = new Map(
                component.map((type) => [
                    type,
                    sha1(
                        this.signature(this.shapeOf(type), (child) =>
                            inside.has(child) ? previous.get(child)! : outsideId(child),
                        ),
                    ),
                ]),
            );

            const nextCount = new Set(classes.values()).size;

            if (nextCount === count) {
                break;
            }

            count = nextCount;
        }

        this.digestCycle(classes);
    }

    /**
     * Serializes the quotient graph breadth-first from its smallest class; strongly connected, it reaches every
     * class. A member's hash adds the number of its class. Every class counts once towards the length, and
     * references back into the cycle count as `BACK_REFERENCE`.
     */
    private digestCycle(classes: Map<ts.Type, string>): void {
        const representatives = new Map<string, ts.Type>();

        for (const [type, classHash] of classes) {
            if (!representatives.has(classHash)) {
                representatives.set(classHash, type);
            }
        }

        const start = [...representatives.keys()].sort(compareText)[0];
        const numbers = new Map([[start, 0]]);
        const queue = [representatives.get(start)!];
        const parts: string[] = [];
        let length = 0;

        for (let index = 0; index < queue.length; index += 1) {
            const shape = this.shapeOf(queue[index]);
            const references = shape.children.map((child, childIndex) => {
                const childClass = classes.get(child);

                return { childIndex, childClass, id: childClass ?? this.digestOf(child).hash };
            });

            if (shape.unordered) {
                references.sort((a, b) => compareText(a.id, b.id));
            }

            length += this.textLength(shape);

            const ids = references.map(({ childIndex, childClass, id }) => {
                if (childClass === undefined) {
                    length = this.childLength(shape, childIndex, length + this.digestOf(shape.children[childIndex]).length);

                    return id;
                }

                let number = numbers.get(childClass);

                if (number === undefined) {
                    number = numbers.size;
                    numbers.set(childClass, number);
                    queue.push(representatives.get(childClass)!);
                }

                length = this.childLength(shape, childIndex, length + BACK_REFERENCE.length);

                return `@${number}`;
            });

            parts.push(
                [
                    shape.kind,
                    shape.key,
                    shape.texts.join('\u0001'),
                    ...ids,
                ].join('\u0000'),
            );
        }

        const groupHash = sha1(`cycle\u0002${parts.join('\u0002')}`);

        for (const [type, classHash] of classes) {
            this.cycles.set(type, classHash);
            this.digests.set(type, { hash: sha1(`${groupHash}@${numbers.get(classHash)}`), length });
        }
    }

    /** Sort key for unordered members: a short rendering of a few levels, then the structural hash. */
    private head(type: ts.Type, depth: number): string {
        const cached = this.heads[depth].get(type);

        if (cached !== undefined) {
            return cached;
        }

        const shape = this.shapeOf(type);
        let head = shape.texts[0];

        if (depth > 0) {
            const order = this.childOrder(shape, depth - 1);

            for (let index = 0; index < order.length && head.length < HEAD_LENGTH; index += 1) {
                head += this.head(shape.children[order[index]], depth - 1) + shape.texts[index + 1];
            }
        }

        head = head.slice(0, HEAD_LENGTH);
        this.heads[depth].set(type, head);

        return head;
    }

    private childOrder(shape: Shape, depth: number): number[] {
        const order = shape.children.map((_child, index) => index);

        if (!shape.unordered) {
            return order;
        }

        const keys = shape.children.map((child) => [
            this.head(child, depth),
            this.structuralHash(child),
        ]);

        return order.sort((a, b) => compareText(keys[a][0], keys[b][0]) || compareText(keys[a][1], keys[b][1]));
    }

    private render(root: ts.Type, budget: number): string {
        let text = '';
        const path = new Map<ts.Type, number>();

        const visit = (type: ts.Type, position: Position): void => {
            const depth = path.get(type);

            if (depth !== undefined) {
                text += `<cycle ^${path.size - depth}>`;

                return;
            }

            const shape = this.shapeOf(type);
            const parenthesized = needsParentheses(shape.kind, position);
            const order = this.childOrder(shape, HEAD_DEPTH);

            text += parenthesized ? `(${shape.texts[0]}` : shape.texts[0];
            path.set(type, path.size);

            for (let index = 0; index < order.length && text.length < budget; index += 1) {
                visit(shape.children[order[index]], shape.positions[order[index]]);
                text += shape.texts[index + 1];
            }

            path.delete(type);

            if (parenthesized) {
                text += ')';
            }
        };

        visit(root, 'plain');

        return text.slice(0, budget);
    }

    private shapeOf(type: ts.Type): Shape {
        let shape = this.shapes.get(type);

        if (!shape) {
            shape = this.createShape(type);
            this.shapes.set(type, shape);
        }

        return shape;
    }

    private createShape(type: ts.Type): Shape {
        const { checker } = this.context;
        const { flags } = type;
        const builder = new ShapeBuilder();

        if (type.aliasSymbol) {
            this.named(builder, type.aliasSymbol, type.aliasTypeArguments ?? []);

            return builder.build('atom');
        }

        if (flags & ts.TypeFlags.EnumLiteral && !(flags & ts.TypeFlags.Union)) {
            builder.key = JSON.stringify((type as ts.StringLiteralType | ts.NumberLiteralType).value);

            return builder.text(printType(this.context, type)).build('atom');
        }

        if (this.isEnum(type)) {
            builder.key = this.enumMembers(type.symbol);

            return builder.text(this.symbolName(type.symbol)).build('atom');
        }

        if (flags & (INTRINSIC_FLAGS | ts.TypeFlags.TypeParameter)) {
            return builder.text(checker.typeToString(type)).build('atom');
        }

        if (flags & ts.TypeFlags.StringLiteral) {
            return builder.text(JSON.stringify((type as ts.StringLiteralType).value)).build('atom');
        }

        if (flags & ts.TypeFlags.NumberLiteral) {
            return builder.text(String((type as ts.NumberLiteralType).value)).build('atom');
        }

        if (flags & ts.TypeFlags.BigIntLiteral) {
            return builder.text(`${internals.pseudoBigIntToString((type as ts.BigIntLiteralType).value)}n`).build('atom');
        }

        if (flags & ts.TypeFlags.Union) {
            return this.members(builder, this.unionMembers(type as ts.UnionType), ' | ', 'union');
        }

        if (flags & ts.TypeFlags.Intersection) {
            return this.members(builder, (type as ts.IntersectionType).types, ' & ', 'intersection');
        }

        if (flags & ts.TypeFlags.Object) {
            const shape = this.objectShape(builder, type as ts.ObjectType);

            if (shape) {
                return shape;
            }
        }

        // Conditional, indexed access, keyof, template literal, string mapping, substitution and unique symbol types
        const text = printType(this.context, type);
        let kind: Kind = 'atom';

        if (/=>| extends | [|&] /.test(text)) {
            kind = 'loose';
        } else if (/^(keyof|typeof|unique|readonly|infer) /.test(text)) {
            kind = 'operator';
        }

        return new ShapeBuilder().text(text).build(kind);
    }

    private members(builder: ShapeBuilder, types: readonly ts.Type[], separator: string, kind: Kind): Shape {
        const position = kind === 'union' ? 'union' : 'intersection';

        return builder.list(types, separator, (member) => builder.child(member, position)).build(kind, true);
    }

    private unionMembers(type: ts.UnionType): readonly ts.Type[] {
        const isBooleanLiteral = (member: ts.Type): boolean =>
            !!(member.flags & ts.TypeFlags.BooleanLiteral) && !(member.flags & ts.TypeFlags.EnumLiteral);
        const booleans = type.types.filter(isBooleanLiteral);

        if (booleans.length < 2) {
            return type.types;
        }

        return [
            ...type.types.filter((member) => !isBooleanLiteral(member)),
            this.context.checker.getBooleanType(),
        ];
    }

    private isEnum(type: ts.Type): boolean {
        if (!(type.flags & ts.TypeFlags.EnumLike) || !type.symbol || !(type.symbol.flags & ts.SymbolFlags.Enum)) {
            return false;
        }

        return this.context.checker.getDeclaredTypeOfSymbol(type.symbol) === type;
    }

    private enumMembers(symbol: ts.Symbol): string {
        const members: string[] = [];

        symbol.exports?.forEach((member) => {
            const declaration = member.valueDeclaration;
            const value =
                declaration && ts.isEnumMember(declaration) ? this.context.checker.getConstantValue(declaration) : undefined;

            members.push(`${member.name}=${JSON.stringify(value) ?? '?'}`);
        });

        return members.sort(compareText).join(',');
    }

    private objectShape(builder: ShapeBuilder, type: ts.ObjectType): Shape | null {
        const { checker } = this.context;
        const { objectFlags } = type;

        if (objectFlags & ts.ObjectFlags.Reference) {
            const reference = type as ts.TypeReference;

            if (checker.isTupleType(reference)) {
                return this.tupleShape(builder, reference as ts.TupleTypeReference);
            }

            if (checker.isArrayType(reference)) {
                const readonly = reference.target.symbol?.name === 'ReadonlyArray';

                builder
                    .text(readonly ? 'readonly ' : '')
                    .child(checker.getTypeArguments(reference)[0], 'array')
                    .text('[]');

                return builder.build(readonly ? 'operator' : 'atom');
            }

            const { target } = reference;

            if (target.objectFlags & (ts.ObjectFlags.Class | ts.ObjectFlags.Interface) && target.symbol) {
                const parameterCount = target.typeParameters?.length ?? 0;

                this.named(builder, target.symbol, checker.getTypeArguments(reference).slice(0, parameterCount));

                return builder.build('atom');
            }
        }

        if (objectFlags & (ts.ObjectFlags.Class | ts.ObjectFlags.Interface) && type.symbol) {
            this.named(builder, type.symbol, []);

            return builder.build('atom');
        }

        const typeSymbol = type.symbol as ts.Symbol | undefined;

        if (
            objectFlags & ts.ObjectFlags.Anonymous &&
            typeSymbol &&
            typeSymbol.flags & (ts.SymbolFlags.Class | ts.SymbolFlags.Enum | ts.SymbolFlags.ValueModule)
        ) {
            builder.key = this.declarationFile(typeSymbol);

            return builder.text(`typeof ${this.symbolName(typeSymbol)}`).build('operator');
        }

        const properties = checker.getPropertiesOfType(type);
        const indexInfos = checker.getIndexInfosOfType(type);

        if (objectFlags & ts.ObjectFlags.Mapped) {
            const { constraintType } = type as { constraintType?: ts.Type };

            if (!constraintType || isGeneric(constraintType)) {
                return null;
            }
        }

        const calls = checker.getSignaturesOfType(type, ts.SignatureKind.Call);
        const constructs = checker.getSignaturesOfType(type, ts.SignatureKind.Construct);

        if (properties.length === 0 && indexInfos.length === 0 && calls.length + constructs.length === 1) {
            this.signatureParts(builder, calls[0] ?? constructs[0], ' => ', calls.length === 0);

            return builder.build('function');
        }

        if (properties.length + indexInfos.length + calls.length + constructs.length === 0) {
            return builder.text('{}').build('atom');
        }

        builder.text('{ ');

        for (const signature of calls) {
            this.signatureParts(builder, signature, ': ', false);
            builder.text('; ');
        }

        for (const signature of constructs) {
            this.signatureParts(builder, signature, ': ', true);
            builder.text('; ');
        }

        for (const info of indexInfos) {
            const parameter = info.declaration?.parameters[0]?.name;

            builder
                .text(
                    `${info.isReadonly ? 'readonly ' : ''}[${parameter && ts.isIdentifier(parameter) ? parameter.text : 'key'}: `,
                )
                .child(info.keyType)
                .text(']: ')
                .child(info.type)
                .text('; ');
        }

        const named = properties.map((property) => ({ property, name: this.propertyName(property) }));

        for (const { property, name } of named.sort((a, b) => compareText(a.name, b.name))) {
            this.propertyParts(builder, property, name);
        }

        return builder.text('}').build('atom');
    }

    private tupleShape(builder: ShapeBuilder, reference: ts.TupleTypeReference): Shape {
        const { target } = reference;
        const elements = this.context.checker.getTypeArguments(reference);

        builder.text(target.readonly ? 'readonly [' : '[');
        builder.list(elements, ', ', (element, index) => {
            const flags = target.elementFlags[index];
            const declaration = target.labeledElementDeclarations?.[index];
            const label = declaration && ts.isIdentifier(declaration.name) ? declaration.name.text : null;
            const optional = flags & ts.ElementFlags.Optional ? '?' : '';
            const spread = flags & ts.ElementFlags.Variable ? '...' : '';

            if (label) {
                builder.text(`${spread}${label}${optional}: `);
            } else {
                builder.text(spread);
            }

            builder.child(element, flags & ts.ElementFlags.Rest || (!label && optional) ? 'array' : 'plain');
            builder.text(flags & ts.ElementFlags.Rest ? '[]' : '');

            if (!label) {
                builder.text(optional);
            }
        });

        return builder.text(']').build(target.readonly ? 'operator' : 'atom');
    }

    private propertyParts(builder: ShapeBuilder, property: ts.Symbol, name: string): void {
        const { checker } = this.context;
        const optional = property.flags & ts.SymbolFlags.Optional ? '?' : '';
        const type = checker.getTypeOfSymbol(property);

        if (property.flags & ts.SymbolFlags.Method) {
            const signatures = checker.getSignaturesOfType(type, ts.SignatureKind.Call);

            if (signatures.length > 0 && checker.getPropertiesOfType(type).length === 0) {
                for (const signature of signatures) {
                    builder.text(`${name}${optional}`);
                    this.signatureParts(builder, signature, ': ', false);
                    builder.text('; ');
                }

                return;
            }
        }

        builder
            .text(`${this.isReadonly(property) ? 'readonly ' : ''}${name}${optional}: `)
            .child(optional ? this.withoutOptionality(type) : type)
            .text('; ');
    }

    private signatureParts(
        builder: ShapeBuilder,
        signature: ts.Signature,
        returnSeparator: string,
        construct: boolean,
    ): void {
        const { checker } = this.context;
        const typeParameters = signature.getTypeParameters() ?? [];
        const parameters = signature.getParameters();
        const minArgumentCount = (signature as { minArgumentCount?: number }).minArgumentCount ?? parameters.length;
        const hasRest = internals.signatureHasRestParameter(signature);

        builder.text(construct ? 'new ' : '');

        if (typeParameters.length > 0) {
            builder
                .text('<')
                .list(typeParameters, ', ', (parameter) => {
                    const constraint = parameter.getConstraint();
                    const fallback = parameter.getDefault();

                    builder.text(parameter.symbol.name);

                    if (constraint) {
                        builder.text(' extends ').child(constraint);
                    }

                    if (fallback) {
                        builder.text(' = ').child(fallback);
                    }
                })
                .text('>');
        }

        builder.text('(');

        if (signature.thisParameter) {
            builder.text('this: ').child(checker.getTypeOfSymbol(signature.thisParameter));
            builder.text(parameters.length > 0 ? ', ' : '');
        }

        builder.list(parameters, ', ', (parameter, index) => {
            const rest = hasRest && index === parameters.length - 1;
            const optional = !rest && index >= minArgumentCount;
            const type = checker.getTypeOfSymbol(parameter);

            builder
                .text(`${rest ? '...' : ''}${parameter.name}${optional ? '?' : ''}: `)
                .child(optional ? this.withoutOptionality(type) : type);
        });

        builder.text(`)${returnSeparator}`);

        const predicate = checker.getTypePredicateOfSignature(signature);

        if (!predicate) {
            builder.child(checker.getReturnTypeOfSignature(signature));

            return;
        }

        const asserts =
            predicate.kind === ts.TypePredicateKind.AssertsThis || predicate.kind === ts.TypePredicateKind.AssertsIdentifier;
        const subject =
            predicate.kind === ts.TypePredicateKind.This || predicate.kind === ts.TypePredicateKind.AssertsThis
                ? 'this'
                : predicate.parameterName;

        builder.text(`${asserts ? 'asserts ' : ''}${subject}`);

        if (predicate.type) {
            builder.text(' is ').child(predicate.type);
        }
    }

    /** Optional members print without the `undefined` their optionality adds, as in the checker's own output. */
    private withoutOptionality(type: ts.Type): ts.Type {
        if (!(type.flags & ts.TypeFlags.Union)) {
            return type;
        }

        const { types } = type as ts.UnionType;
        const hasUndefined = types.some((member) => member.flags & ts.TypeFlags.Undefined);
        const keepsNull = types.some((member) => member.flags & (ts.TypeFlags.Null | ts.TypeFlags.Instantiable));

        return hasUndefined && !keepsNull ? this.context.checker.getNonNullableType(type) : type;
    }

    private isReadonly(property: ts.Symbol): boolean {
        if (internals.getCheckFlags(property) & internals.CheckFlags.Readonly) {
            return true;
        }

        if (internals.getDeclarationModifierFlagsFromSymbol(property) & ts.ModifierFlags.Readonly) {
            return true;
        }

        return !!(property.flags & ts.SymbolFlags.GetAccessor) && !(property.flags & ts.SymbolFlags.SetAccessor);
    }

    /** Escaped names of symbol-keyed members carry symbol ids, e.g. `__@iterator@12`. */
    private propertyName(property: ts.Symbol): string {
        const escaped = property.escapedName as string;

        if (escaped.startsWith('__@') || escaped.startsWith('__#')) {
            return this.context.checker.symbolToString(property);
        }

        const { name } = property;

        return internals.isIdentifierText(name, ts.ScriptTarget.Latest) || /^\d+$/.test(name) ? name : JSON.stringify(name);
    }

    private named(builder: ShapeBuilder, symbol: ts.Symbol, typeArguments: readonly ts.Type[]): void {
        builder.key = this.declarationFile(symbol);
        builder.text(this.symbolName(symbol));

        if (typeArguments.length > 0) {
            builder
                .text('<')
                .list(typeArguments, ', ', (argument) => builder.child(argument))
                .text('>');
        }
    }

    private symbolName(symbol: ts.Symbol): string {
        const qualified = this.context.checker.getFullyQualifiedName(symbol);
        const moduleMember = /^"([^"]+)"\.(.+)$/s.exec(qualified);

        return normalizePrintedType(
            this.context,
            moduleMember ? `import("${moduleMember[1]}").${moduleMember[2]}` : qualified,
        );
    }

    /** Tells apart local declarations of the same name in different files. */
    private declarationFile(symbol: ts.Symbol): string {
        const fileName = symbol.declarations?.[0]?.getSourceFile().fileName;

        return fileName ? normalizePrintedType(this.context, `import("${fileName}")`) : '';
    }
}

const digesters = new WeakMap<DigestContext, TypeDigester>();

/**
 * Hash, length and display of the canonical rendering of a type: unions and intersections are sorted,
 * object members ordered by name and named types referenced by name, as in the printed type text. The
 * length is that of the full rendering; inside a reference cycle every type counts once.
 */
export function digestType(context: DigestContext, type: ts.Type): TypeDigest {
    let digester = digesters.get(context);

    if (!digester) {
        digester = new TypeDigester(context);
        digesters.set(context, digester);
    }

    return digester.digest(type);
}

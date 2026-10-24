/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import type {
    CallSignatureEntry,
    ParameterEntry,
    ShopwareObjectNode,
    ShopwareObjectNodeKind,
    SnapshotDiagnostic,
    Visibility,
} from '../types';
import {
    collectTags,
    declarationChain,
    deprecationFromTags,
    hasPrivateModifier,
    isConstantDeclaration,
    isExternalDeclaration,
    isExternalFile,
    isExternalSymbol,
    locationOf,
    strictestVisibility,
    typeSourceOf,
    visibilityFromTags,
} from './declarations';
import { printSignature, printType } from '../type-printer';

/** Safety net only: the real admin tree is complete well before this depth. */
export const MAX_DEPTH = 8;

const SUMMARY_NAME_COUNT = 3;
const SHORT_TEXT_LENGTH = 120;

const LITERAL_FLAGS =
    ts.TypeFlags.StringLiteral |
    ts.TypeFlags.NumberLiteral |
    ts.TypeFlags.BigIntLiteral |
    ts.TypeFlags.BooleanLiteral |
    ts.TypeFlags.EnumLiteral;

interface ParentFrame {
    path: string;
    visibility: Visibility;
    depth: number;
    ancestors: ReadonlySet<ts.Type>;
}

interface MemberGroup {
    type: ts.Type;
    separator: '.' | '#';
    classMembers: boolean;
}

export class ShopwareObjectWalker {
    readonly diagnostics: SnapshotDiagnostic[] = [];

    private readonly checker: ts.TypeChecker;

    constructor(private readonly context: AnalysisContext) {
        this.checker = context.checker;
    }

    walkRoot(rootType: ts.Type, rootDeclaration: ts.Node | undefined): ShopwareObjectNode {
        const frame: ParentFrame = {
            path: 'Shopware',
            visibility: 'public',
            depth: 0,
            ancestors: new Set([rootType]),
        };
        const children = this.walkGroups(frame, [{ type: rootType, separator: '.', classMembers: false }]);

        return {
            path: 'Shopware',
            name: 'Shopware',
            kind: 'object',
            signature: this.objectSummary(children.map(({ name }) => name)),
            callSignatures: [],
            typeSource: typeSourceOf(rootDeclaration),
            truncated: false,
            children,
            visibility: 'public',
            deprecation: null,
            location: locationOf(this.context, rootDeclaration),
        };
    }

    private walkGroups(frame: ParentFrame, groups: MemberGroup[]): ShopwareObjectNode[] {
        return groups.flatMap(({ type, separator, classMembers }) =>
            this.membersOf(type).flatMap((symbol) => {
                try {
                    return [this.memberNode(symbol, frame, separator, classMembers)];
                } catch (error) {
                    this.diagnostics.push({
                        file: null,
                        message: `Cannot describe ${frame.path}${separator}${symbol.name}: ${error instanceof Error ? error.message : String(error)}`,
                    });

                    return [];
                }
            }),
        );
    }

    private membersOf(type: ts.Type): ts.Symbol[] {
        return this.checker
            .getPropertiesOfType(type)
            .filter(
                (symbol) =>
                    symbol.name !== 'prototype' &&
                    !symbol.name.startsWith('#') &&
                    !symbol.name.startsWith('__#') &&
                    !symbol.name.startsWith('__@') &&
                    !isExternalSymbol(this.context, symbol),
            );
    }

    private memberNode(
        symbol: ts.Symbol,
        parent: ParentFrame,
        separator: '.' | '#',
        classMember: boolean,
    ): ShopwareObjectNode {
        const chain = declarationChain(this.checker, symbol);
        const ownDeclaration = chain[0];
        const declaration = [...chain].reverse().find((node) => !isExternalDeclaration(this.context, node));
        const tags = collectTags(chain);
        const path = `${parent.path}${separator}${symbol.name}`;
        // Only the exposure path decides visibility: alias targets often carry a lint-required `@private`.
        const visibility = strictestVisibility(
            parent.visibility,
            visibilityFromTags(collectTags(symbol.declarations ?? [])),
            symbol.name.startsWith('_') || hasPrivateModifier(ownDeclaration) ? 'private' : 'public',
        );

        const type = this.checker.getTypeOfSymbol(symbol);
        const valueType = this.checker.getNonNullableType(type);
        const node: ShopwareObjectNode = {
            path,
            name: symbol.name,
            kind: this.kindOf(symbol, valueType, chain, classMember),
            signature: '',
            callSignatures: [],
            typeSource: typeSourceOf(declaration),
            truncated: false,
            children: [],
            visibility,
            deprecation: tags.length > 0 ? deprecationFromTags(tags) : null,
            location: locationOf(this.context, declaration),
        };

        this.describe(node, type, valueType, {
            path,
            visibility,
            depth: parent.depth + 1,
            ancestors: parent.ancestors,
        });

        return node;
    }

    private kindOf(symbol: ts.Symbol, type: ts.Type, chain: ts.Node[], classMember: boolean): ShopwareObjectNodeKind {
        if (symbol.flags & ts.SymbolFlags.GetAccessor) {
            return 'getter';
        }

        if (type.getConstructSignatures().length > 0) {
            return 'class';
        }

        if (type.getCallSignatures().length > 0) {
            return classMember ? 'method' : 'function';
        }

        if (this.isInstanceType(type)) {
            return 'instance';
        }

        if (this.isWalkableObject(type)) {
            return 'object';
        }

        if (this.isLiteralType(type) || chain.some((declaration) => isConstantDeclaration(declaration))) {
            return 'constant';
        }

        return 'property';
    }

    private describe(node: ShopwareObjectNode, type: ts.Type, valueType: ts.Type, frame: ParentFrame): void {
        switch (node.kind) {
            case 'class':
                this.describeClass(node, valueType, frame);
                return;
            case 'function':
            case 'method':
                this.describeCallable(node, valueType, frame);
                return;
            case 'object':
            case 'instance':
            case 'getter':
                this.describeContainer(node, type, valueType, frame);
                return;
            default:
                node.signature = this.fullText(type);
        }
    }

    private describeClass(node: ShopwareObjectNode, type: ts.Type, frame: ParentFrame): void {
        const constructSignatures = type.getConstructSignatures();
        const instanceType = constructSignatures[0].getReturnType();
        node.signature = `class ${this.className(type, node.name)}`;

        if (this.isPackageType(type)) {
            node.truncated = true;
            return;
        }

        node.callSignatures = constructSignatures.map((signature) => this.callSignatureEntry(signature));

        if (!this.canDescend(node, frame, [type, instanceType])) {
            return;
        }

        node.children = this.walkGroups(this.childFrame(frame, [type, instanceType]), [
            { type, separator: '.', classMembers: true },
            { type: instanceType, separator: '#', classMembers: true },
        ]);
    }

    private describeCallable(node: ShopwareObjectNode, type: ts.Type, frame: ParentFrame): void {
        const signatures = type.getCallSignatures();
        node.callSignatures = signatures.map((signature) => this.callSignatureEntry(signature));
        node.signature = signatures.map((signature) => printSignature(this.context, signature)).join('\n');

        if (this.isPackageType(type) || this.membersOf(type).length === 0 || !this.canDescend(node, frame, [type])) {
            return;
        }

        node.children = this.walkGroups(this.childFrame(frame, [type]), [
            { type, separator: '.', classMembers: false },
        ]);
    }

    private describeContainer(node: ShopwareObjectNode, type: ts.Type, valueType: ts.Type, frame: ParentFrame): void {
        const walkable = this.isInstanceType(valueType) || this.isWalkableObject(valueType);

        if (!walkable) {
            node.signature = this.fullText(type);
            return;
        }

        if (this.isPackageType(valueType)) {
            node.signature = this.shortText(type);
            node.truncated = true;
            return;
        }

        const memberNames = this.membersOf(valueType).map(({ name }) => name);

        if (memberNames.length === 0) {
            node.signature = this.fullText(type);
            return;
        }

        node.signature = node.kind === 'instance' ? this.shortText(valueType) : this.objectSummary(memberNames);

        if (!this.canDescend(node, frame, [valueType])) {
            return;
        }

        node.children = this.walkGroups(this.childFrame(frame, [valueType]), [
            { type: valueType, separator: '.', classMembers: node.kind === 'instance' },
        ]);
    }

    private canDescend(node: ShopwareObjectNode, frame: ParentFrame, types: ts.Type[]): boolean {
        if (frame.depth >= MAX_DEPTH || types.some((type) => frame.ancestors.has(type))) {
            node.truncated = true;
            return false;
        }

        return true;
    }

    private childFrame(frame: ParentFrame, types: ts.Type[]): ParentFrame {
        return { ...frame, ancestors: new Set([...frame.ancestors, ...types]) };
    }

    private callSignatureEntry(signature: ts.Signature): CallSignatureEntry {
        return {
            parameters: signature.getParameters().map((parameter) => this.parameterEntry(parameter)),
            returnType: this.fullText(signature.getReturnType()),
        };
    }

    private parameterEntry(parameter: ts.Symbol): ParameterEntry {
        const declaration = parameter.valueDeclaration;
        const isParameter = declaration !== undefined && ts.isParameter(declaration);

        return {
            name: parameter.name,
            type: this.fullText(this.checker.getTypeOfSymbol(parameter)),
            optional: isParameter && this.checker.isOptionalParameter(declaration) && !declaration.dotDotDotToken,
            rest: isParameter && declaration.dotDotDotToken !== undefined,
        };
    }

    private className(type: ts.Type, fallback: string): string {
        const name = type
            .getSymbol()
            ?.declarations?.map((declaration) => ts.getNameOfDeclaration(declaration))
            .find((identifier) => identifier !== undefined && ts.isIdentifier(identifier));

        return name && ts.isIdentifier(name) ? name.text : fallback;
    }

    private objectSummary(names: string[]): string {
        if (names.length === 0) {
            return '{}';
        }

        const shown = names.slice(0, SUMMARY_NAME_COUNT);

        return `{ ${shown.join(', ')}${names.length > shown.length ? ', …' : ''} }`;
    }

    private isInstanceType(type: ts.Type): boolean {
        const symbol = type.getSymbol();

        return (
            symbol !== undefined &&
            (symbol.flags & ts.SymbolFlags.Class) !== 0 &&
            (type.flags & ts.TypeFlags.Object) !== 0 &&
            type.getConstructSignatures().length === 0 &&
            type.getCallSignatures().length === 0
        );
    }

    private isWalkableObject(type: ts.Type): boolean {
        if (this.checker.isArrayType(type) || this.checker.isTupleType(type)) {
            return false;
        }

        if (type.isUnionOrIntersection()) {
            return type.types.every((member) => this.isWalkableObject(member));
        }

        return (type.flags & ts.TypeFlags.Object) !== 0;
    }

    private isLiteralType(type: ts.Type): boolean {
        if (type.isUnion()) {
            return false;
        }

        return (type.flags & LITERAL_FLAGS) !== 0;
    }

    /**
     * Types from the default lib (`Readonly<…>`, `Map<…>`) are walked instead: their own members are filtered out anyway.
     * Admin augmentations of a package type (e.g. Vue's `App`) do not make it an admin type.
     */
    private isPackageType(type: ts.Type): boolean {
        const symbol = type.aliasSymbol ?? type.getSymbol();

        return (symbol?.declarations ?? []).some((declaration) => {
            const sourceFile = declaration.getSourceFile();

            return !this.context.program.isSourceFileDefaultLibrary(sourceFile) && isExternalFile(this.context, sourceFile);
        });
    }

    private fullText(type: ts.Type): string {
        return printType(this.context, type);
    }

    private shortText(type: ts.Type): string {
        const text = printType(this.context, type, undefined, ts.TypeFormatFlags.None);

        return text.length > SHORT_TEXT_LENGTH ? `${text.slice(0, SHORT_TEXT_LENGTH - 1)}…` : text;
    }
}

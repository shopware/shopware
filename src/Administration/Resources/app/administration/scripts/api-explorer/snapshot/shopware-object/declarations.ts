/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import type { Deprecation, SourceLocation, TypeSource, Visibility } from '../types';

const MAX_ALIAS_HOPS = 10;

const VISIBILITY_RANK: Record<Visibility, number> = {
    public: 0,
    experimental: 1,
    internal: 2,
    private: 3,
};

export function strictestVisibility(...visibilities: Visibility[]): Visibility {
    return visibilities.reduce((strictest, visibility) =>
        VISIBILITY_RANK[visibility] > VISIBILITY_RANK[strictest] ? visibility : strictest,
    );
}

export function isExternalFile(context: AnalysisContext, sourceFile: ts.SourceFile): boolean {
    if (
        context.program.isSourceFileDefaultLibrary(sourceFile) ||
        context.program.isSourceFileFromExternalLibrary(sourceFile)
    ) {
        return true;
    }

    const relative = context.toRelativePath(sourceFile.fileName);

    return (
        relative.startsWith('node_modules/') ||
        relative.startsWith('/') ||
        relative.includes('/node_modules/') ||
        /(^|\/)lib\.[^/]*\.d\.ts$/.test(relative)
    );
}

export function isExternalDeclaration(context: AnalysisContext, declaration: ts.Node): boolean {
    return isExternalFile(context, declaration.getSourceFile());
}

/** Symbols without declarations count as internal: nothing proves they come from a package. */
export function isExternalSymbol(context: AnalysisContext, symbol: ts.Symbol | undefined): boolean {
    const declarations = symbol?.declarations ?? [];

    return declarations.length > 0 && declarations.every((declaration) => isExternalDeclaration(context, declaration));
}

function resolveAlias(checker: ts.TypeChecker, symbol: ts.Symbol | undefined): ts.Symbol | undefined {
    if (symbol && symbol.flags & ts.SymbolFlags.Alias) {
        return checker.getAliasedSymbol(symbol);
    }

    return symbol;
}

function skipOuterExpressions(expression: ts.Expression): ts.Expression {
    let current = expression;

    while (
        ts.isParenthesizedExpression(current) ||
        ts.isAsExpression(current) ||
        ts.isSatisfiesExpression(current) ||
        ts.isTypeAssertionExpression(current) ||
        ts.isNonNullExpression(current)
    ) {
        current = current.expression;
    }

    return current;
}

function initializerOf(declaration: ts.Node): ts.Expression | undefined {
    if (
        ts.isPropertyAssignment(declaration) ||
        ts.isPropertyDeclaration(declaration) ||
        ts.isVariableDeclaration(declaration)
    ) {
        return declaration.initializer;
    }

    if (ts.isExportAssignment(declaration)) {
        return declaration.expression;
    }

    return undefined;
}

function followDeclaration(checker: ts.TypeChecker, declaration: ts.Node): ts.Node | undefined {
    let target: ts.Symbol | undefined;

    if (ts.isShorthandPropertyAssignment(declaration)) {
        target = checker.getShorthandAssignmentValueSymbol(declaration);
    } else {
        const initializer = initializerOf(declaration);
        const expression = initializer && skipOuterExpressions(initializer);

        if (expression && ts.isIdentifier(expression)) {
            target = checker.getSymbolAtLocation(expression);
        } else if (expression && ts.isPropertyAccessExpression(expression)) {
            target = checker.getSymbolAtLocation(expression.name);
        }
    }

    const resolved = resolveAlias(checker, target);

    return resolved?.valueDeclaration ?? resolved?.declarations?.[0];
}

/**
 * The member's own declaration first, then each declaration reached by following
 * `name: other`, `{ other }`, `const x = other` and import aliases.
 */
export function declarationChain(checker: ts.TypeChecker, symbol: ts.Symbol): ts.Node[] {
    const first = symbol.valueDeclaration ?? symbol.declarations?.[0];

    if (!first) {
        return [];
    }

    const chain: ts.Node[] = [first];

    for (let hop = 0; hop < MAX_ALIAS_HOPS; hop += 1) {
        const next = followDeclaration(checker, chain[chain.length - 1]);

        // An accessor's docs describe the accessor, not the value it returns (`Store = Store.instance`).
        if (!next || chain.includes(next) || ts.isAccessor(next)) {
            break;
        }

        chain.push(next);
    }

    return chain;
}

function jsDocTagsOf(declaration: ts.Node): readonly ts.JSDocTag[] {
    return ts.isSourceFile(declaration) ? [] : ts.getJSDocTags(declaration);
}

function tagComment(tag: ts.JSDocTag): string {
    return (ts.getTextOfJSDocComment(tag.comment) ?? '').replace(/\s+/g, ' ').trim();
}

export function collectTags(declarations: ts.Node[]): ts.JSDocTag[] {
    return declarations.flatMap((declaration) => [...jsDocTagsOf(declaration)]);
}

export function visibilityFromTags(tags: ts.JSDocTag[]): Visibility {
    const names = new Set(tags.map((tag) => tag.tagName.text));

    if (names.has('private')) {
        return 'private';
    }

    if (names.has('internal')) {
        return 'internal';
    }

    if (names.has('experimental')) {
        return 'experimental';
    }

    return 'public';
}

export function deprecationFromTags(tags: ts.JSDocTag[]): Deprecation | null {
    const tag = tags.find(({ tagName }) => tagName.text === 'deprecated');

    if (!tag) {
        return null;
    }

    const comment = tagComment(tag);
    const match = /^tag:(\S+)\s*(?:-\s*)?(.*)$/.exec(comment);

    if (!match) {
        return { version: null, message: comment };
    }

    return { version: match[1], message: match[2].trim() };
}

function hasJsDocTypes(declaration: ts.Node): boolean {
    if (ts.isSourceFile(declaration)) {
        return false;
    }

    return ts
        .getJSDocTags(declaration)
        .some(
            (tag) =>
                ts.isJSDocTypeTag(tag) ||
                ts.isJSDocReturnTag(tag) ||
                ts.isJSDocTypedefTag(tag) ||
                (ts.isJSDocParameterTag(tag) && tag.typeExpression !== undefined),
        );
}

export function typeSourceOf(declaration: ts.Node | undefined): TypeSource {
    if (!declaration) {
        return 'unknown';
    }

    const fileName = declaration.getSourceFile().fileName;

    if (/\.[cm]?tsx?$/.test(fileName)) {
        return 'typescript';
    }

    if (/\.[cm]?jsx?$/.test(fileName)) {
        return hasJsDocTypes(declaration) ? 'jsdoc' : 'inferred';
    }

    return 'unknown';
}

export function locationOf(context: AnalysisContext, declaration: ts.Node | undefined): SourceLocation | null {
    if (!declaration) {
        return null;
    }

    const sourceFile = declaration.getSourceFile();
    const position = ts.isSourceFile(declaration) ? 0 : declaration.getStart(sourceFile);

    return {
        file: context.toRelativePath(sourceFile.fileName),
        line: sourceFile.getLineAndCharacterOfPosition(position).line + 1,
    };
}

function isLiteralExpression(expression: ts.Expression): boolean {
    const inner = skipOuterExpressions(expression);

    if (ts.isArrayLiteralExpression(inner)) {
        return inner.elements.every((element) => !ts.isSpreadElement(element) && isLiteralExpression(element));
    }

    if (ts.isPrefixUnaryExpression(inner)) {
        return ts.isNumericLiteral(inner.operand) || ts.isBigIntLiteral(inner.operand);
    }

    return (
        ts.isStringLiteral(inner) ||
        ts.isNumericLiteral(inner) ||
        ts.isBigIntLiteral(inner) ||
        ts.isNoSubstitutionTemplateLiteral(inner) ||
        inner.kind === ts.SyntaxKind.TrueKeyword ||
        inner.kind === ts.SyntaxKind.FalseKeyword ||
        inner.kind === ts.SyntaxKind.NullKeyword
    );
}

export function isConstantDeclaration(declaration: ts.Node | undefined): boolean {
    if (!declaration || ts.isSourceFile(declaration)) {
        return false;
    }

    if (ts.isEnumMember(declaration)) {
        return true;
    }

    if (
        ts.isDeclarationStatement(declaration) ||
        ts.isPropertyDeclaration(declaration) ||
        ts.isPropertySignature(declaration)
    ) {
        if (ts.getCombinedModifierFlags(declaration as ts.Declaration) & ts.ModifierFlags.Readonly) {
            return true;
        }
    }

    const initializer = initializerOf(declaration);

    return initializer !== undefined && isLiteralExpression(initializer);
}

export function hasPrivateModifier(declaration: ts.Node | undefined): boolean {
    if (!declaration || ts.isSourceFile(declaration) || !ts.canHaveModifiers(declaration)) {
        return false;
    }

    return (ts.getCombinedModifierFlags(declaration as ts.Declaration) & ts.ModifierFlags.Private) !== 0;
}

/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import type { GlobalUse } from './model';

/**
 * A name destructured from the `Shopware` global, such as `Mixin` in `const { Mixin } = Shopware;`
 * or `Criteria` in `const { Criteria } = Shopware.Data;`, with the path it stands for.
 */
export type Alias = {
    name: string;
    path: string[];
    element: ts.BindingElement;
    statement: ts.VariableStatement;
    references: ts.Identifier[];
};

/** Where a file reaches the `Shopware` global: the uses a rewrite can take, and the names destructured from it. */
export type GlobalScan = {
    uses: GlobalUse[];
    aliases: Alias[];
    /** Every name the file declares, with how often, so a rewrite never imports a name that is taken. */
    declared: Map<string, number>;
};

const walk = (node: ts.Node, visit: (node: ts.Node) => void) => {
    visit(node);
    node.forEachChild((child) => walk(child, visit));
};

/** The names a declaration introduces, also those inside binding patterns. */
const bindingNames = (name: ts.BindingName): ts.Identifier[] =>
    ts.isIdentifier(name)
        ? [name]
        : name.elements.flatMap((element) => (ts.isOmittedExpression(element) ? [] : bindingNames(element.name)));

/** Counts every name the file declares in any scope. */
export function collectDeclaredNames(source: ts.SourceFile): Map<string, number> {
    const declared = new Map<string, number>();
    const add = (identifier: ts.Identifier | undefined) => {
        if (identifier) {
            declared.set(identifier.text, (declared.get(identifier.text) ?? 0) + 1);
        }
    };

    walk(source, (node) => {
        if (ts.isVariableDeclaration(node) || ts.isParameter(node)) {
            bindingNames(node.name).forEach(add);
        } else if (
            ts.isFunctionDeclaration(node) ||
            ts.isFunctionExpression(node) ||
            ts.isClassDeclaration(node) ||
            ts.isClassExpression(node) ||
            ts.isEnumDeclaration(node) ||
            ts.isTypeAliasDeclaration(node) ||
            ts.isInterfaceDeclaration(node)
        ) {
            add(node.name && ts.isIdentifier(node.name) ? node.name : undefined);
        } else if (ts.isImportClause(node) || ts.isNamespaceImport(node) || ts.isImportSpecifier(node)) {
            add(node.name);
        } else if (ts.isCatchClause(node) && node.variableDeclaration) {
            bindingNames(node.variableDeclaration.name).forEach(add);
        }
    });

    return declared;
}

/** Whether an identifier reads a variable, not a property name, a key or a declaration. */
export function isReference(identifier: ts.Identifier): boolean {
    const parent = identifier.parent;

    if (ts.isPropertyAccessExpression(parent) && parent.name === identifier) {
        return false;
    }

    if (
        (ts.isPropertyAssignment(parent) ||
            ts.isMethodDeclaration(parent) ||
            ts.isPropertyDeclaration(parent) ||
            ts.isGetAccessor(parent) ||
            ts.isSetAccessor(parent) ||
            ts.isPropertySignature(parent) ||
            ts.isMethodSignature(parent) ||
            ts.isEnumMember(parent)) &&
        parent.name === identifier
    ) {
        return false;
    }

    if (ts.isBindingElement(parent) && (parent.name === identifier || parent.propertyName === identifier)) {
        return false;
    }

    if (
        (ts.isVariableDeclaration(parent) ||
            ts.isParameter(parent) ||
            ts.isFunctionDeclaration(parent) ||
            ts.isFunctionExpression(parent) ||
            ts.isClassDeclaration(parent) ||
            ts.isImportClause(parent) ||
            ts.isImportSpecifier(parent) ||
            ts.isNamespaceImport(parent) ||
            ts.isExportSpecifier(parent) ||
            ts.isLabeledStatement(parent) ||
            ts.isBreakOrContinueStatement(parent) ||
            ts.isTypeAliasDeclaration(parent) ||
            ts.isInterfaceDeclaration(parent)) &&
        (parent as { name?: ts.Node }).name === identifier
    ) {
        return false;
    }

    return !ts.isQualifiedName(parent) && !ts.isTypeReferenceNode(parent);
}

/**
 * The path of a static member chain rooted at `Shopware` or at an alias, such as `['Data', 'Criteria']`
 * for `Shopware.Data.Criteria`, or null for anything else, also for optional chaining.
 */
const readChain = (expression: ts.Expression, aliases: Map<string, Alias>): string[] | null => {
    if (ts.isIdentifier(expression)) {
        if (expression.text === 'Shopware') {
            return [];
        }

        return aliases.get(expression.text)?.path ?? null;
    }

    if (ts.isPropertyAccessExpression(expression) && !expression.questionDotToken && ts.isIdentifier(expression.name)) {
        const object = readChain(expression.expression, aliases);
        return object ? [...object, expression.name.text] : null;
    }

    return null;
};

/** The path of a static chain rooted at `Shopware` itself, such as `['Utils', 'debug']`, or null. */
export const readGlobalChain = (expression: ts.Expression) => readChain(expression, new Map());

/** The aliases a `const { A, B: c } = <chain>;` statement declares, or none when any part of it is not plain. */
const readDestructure = (
    statement: ts.VariableStatement,
    aliases: Map<string, Alias>,
    declared: Map<string, number>,
): Alias[] => {
    const list = statement.declarationList;
    const isConst = (list.flags & ts.NodeFlags.Const) !== 0;
    const isExported = statement.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword);
    if (!isConst || isExported || list.declarations.length !== 1) {
        return [];
    }

    const [declaration] = list.declarations;
    const chain = declaration.initializer ? readChain(declaration.initializer, aliases) : null;
    if (!chain || !ts.isObjectBindingPattern(declaration.name) || declaration.name.elements.length === 0) {
        return [];
    }

    const elements = declaration.name.elements;
    const isPlain = elements.every(
        (element) =>
            !element.dotDotDotToken &&
            !element.initializer &&
            ts.isIdentifier(element.name) &&
            (!element.propertyName || ts.isIdentifier(element.propertyName)) &&
            declared.get(element.name.text) === 1,
    );
    if (!isPlain) {
        return [];
    }

    return elements.map((element) => {
        const name = (element.name as ts.Identifier).text;
        const property = element.propertyName ? (element.propertyName as ts.Identifier).text : name;

        return { name, path: [...chain, property], element, statement, references: [] };
    });
};

/**
 * Finds where a file reaches the `Shopware` global: each static chain such as `Shopware.Data.Criteria`,
 * and each use of a name destructured from it, such as `Mixin.getByName` after `const { Mixin } = Shopware;`.
 * A destructured name counts only when the file declares it once, so every reference means the same.
 * A file that declares a `Shopware` of its own has no uses.
 *
 * @example
 * scanGlobalUses(parse("const { Mixin } = Shopware;\nexport default { mixins: [Mixin.getByName('x')] };"));
 * // → { uses: [{ path: ['Mixin', 'getByName'], call: <Mixin.getByName('x')>, … }], aliases: [Mixin], … }
 */
export function scanGlobalUses(source: ts.SourceFile): GlobalScan {
    const declared = collectDeclaredNames(source);
    if (declared.has('Shopware')) {
        return { uses: [], aliases: [], declared };
    }

    const aliases = new Map<string, Alias>();
    walk(source, (node) => {
        if (ts.isVariableStatement(node)) {
            readDestructure(node, aliases, declared).forEach((alias) => aliases.set(alias.name, alias));
        }
    });

    const aliasInitializers = new Set(
        [...aliases.values()].map((alias) => alias.statement.declarationList.declarations[0].initializer),
    );
    const uses: GlobalUse[] = [];

    walk(source, (node) => {
        if (!ts.isIdentifier(node) || !isReference(node)) {
            return;
        }

        const alias = aliases.get(node.text);
        if (node.text !== 'Shopware' && !alias) {
            return;
        }

        alias?.references.push(node);

        let chain: ts.Expression = node;
        while (
            ts.isPropertyAccessExpression(chain.parent) &&
            chain.parent.expression === chain &&
            !chain.parent.questionDotToken &&
            ts.isIdentifier(chain.parent.name)
        ) {
            chain = chain.parent;
        }

        if (aliasInitializers.has(chain)) {
            return;
        }

        const path = readChain(chain, aliases);
        if (!path) {
            return;
        }

        const parent = chain.parent;
        const call = ts.isCallExpression(parent) && parent.expression === chain ? parent : undefined;
        uses.push({ node: chain, path, call });
    });

    return { uses, aliases: [...aliases.values()], declared };
}

/** Whether a chain is written to, such as `Shopware.Context.api = {}` or `delete Shopware.Utils.x`. */
export function isWritten(node: ts.Expression): boolean {
    const parent = node.parent;

    if (ts.isBinaryExpression(parent) && parent.left === node) {
        return (
            parent.operatorToken.kind >= ts.SyntaxKind.FirstAssignment &&
            parent.operatorToken.kind <= ts.SyntaxKind.LastAssignment
        );
    }

    return (
        ts.isDeleteExpression(parent) ||
        ((ts.isPrefixUnaryExpression(parent) || ts.isPostfixUnaryExpression(parent)) &&
            [ts.SyntaxKind.PlusPlusToken, ts.SyntaxKind.MinusMinusToken].includes(parent.operator))
    );
}

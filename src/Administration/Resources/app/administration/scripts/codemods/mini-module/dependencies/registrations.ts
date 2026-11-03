/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

/** The registries whose entries `src/` looks up by a written name. */
export type RegistryKind = 'Store' | 'Filter' | 'Mixin';

/**
 * A call that registers a store, filter or mixin under a name, found in its file:
 * `literal` is the written name, or null when the file passes an exported constant, `exportName`.
 * `statement` is the top-level statement around the call, before which a constant can be added.
 * `isDefaultExport` tells whether the file exports what the call returns as its default.
 */
export type RegisterCall = {
    statement: ts.Statement;
    literal: ts.StringLiteral | null;
    exportName: string | null;
    isDefaultExport: boolean;
};

/** The ways the Administration writes the register call of each registry. */
export const REGISTER_CALLEES: Record<RegistryKind, string[]> = {
    Store: ['Shopware.Store.register', 'Store.register', 'Store.instance.register'],
    Filter: ['Shopware.Filter.register', 'Filter.register'],
    Mixin: ['Shopware.Mixin.register', 'Mixin.register'],
};

const calleeText = (callee: ts.Expression): string | null => {
    if (ts.isIdentifier(callee)) {
        return callee.text;
    }

    if (ts.isPropertyAccessExpression(callee)) {
        const object = calleeText(callee.expression);
        return object === null ? null : `${object}.${callee.name.text}`;
    }

    return null;
};

const isRegisterCallee = (callee: ts.Expression, kind: RegistryKind) =>
    REGISTER_CALLEES[kind].includes(calleeText(callee) ?? '');

/** The string constants the file exports at the top level, by name. */
const exportedStrings = (source: ts.SourceFile) =>
    new Map(
        source.statements.flatMap((statement) => {
            const isExported =
                ts.isVariableStatement(statement) &&
                statement.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword);

            return isExported
                ? statement.declarationList.declarations.flatMap((declaration) =>
                      ts.isIdentifier(declaration.name) &&
                      declaration.initializer &&
                      ts.isStringLiteral(declaration.initializer)
                          ? [[declaration.name.text, declaration.initializer.text] as const]
                          : [],
                  )
                : [];
        }),
    );

/** The expression that names what the call registers: the first argument, or the `id` of a store's options. */
const nameArgument = (call: ts.CallExpression, kind: RegistryKind): ts.Expression | undefined => {
    const [first] = call.arguments;
    if (kind === 'Store' && first && ts.isObjectLiteralExpression(first)) {
        const id = first.properties.find(
            (property): property is ts.PropertyAssignment =>
                ts.isPropertyAssignment(property) && ts.isIdentifier(property.name) && property.name.text === 'id',
        );

        return id?.initializer;
    }

    return first;
};

const isInsideFunction = (node: ts.Node) => {
    for (let parent = node.parent; parent && !ts.isSourceFile(parent); parent = parent.parent) {
        if (ts.isFunctionLike(parent)) {
            return true;
        }
    }

    return false;
};

/**
 * Finds the call in a file that registers the store, filter or mixin with the given name at load time,
 * written as one of the `REGISTER_CALLEES`, such as `Shopware.Store.register(…)` or a destructured `Store.register(…)`.
 *
 * @example
 * findRegisterCall(parse("Shopware.Filter.register('date', (value) => value);"), 'Filter', 'date');
 * // → { literal: <'date'>, exportName: null, isDefaultExport: false, statement: <…> }
 */
export function findRegisterCall(source: ts.SourceFile, kind: RegistryKind, name: string): RegisterCall | null {
    const strings = exportedStrings(source);
    let found: RegisterCall | null = null;

    const visit = (node: ts.Node) => {
        if (found) {
            return;
        }

        if (ts.isCallExpression(node) && isRegisterCallee(node.expression, kind) && !isInsideFunction(node)) {
            const argument = nameArgument(node, kind);
            const literal = argument && ts.isStringLiteral(argument) && argument.text === name ? argument : null;
            const exportName =
                argument && ts.isIdentifier(argument) && strings.get(argument.text) === name ? argument.text : null;

            if (literal || exportName) {
                let statement: ts.Node = node;
                while (!ts.isSourceFile(statement.parent)) {
                    statement = statement.parent;
                }

                found = {
                    statement: statement as ts.Statement,
                    literal,
                    exportName,
                    isDefaultExport: ts.isExportAssignment(statement) && statement.expression === node,
                };
                return;
            }
        }

        node.forEachChild(visit);
    };

    visit(source);
    return found;
}

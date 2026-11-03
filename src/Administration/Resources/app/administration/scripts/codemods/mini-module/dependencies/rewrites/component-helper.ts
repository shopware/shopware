/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import type { Dependency, Rewrite, UseRewrite } from '../model';
import { isPath, replaceNode, removalRange } from './helpers';

/**
 * The helpers `Component.getComponentHelper()` hands out are imported from the modules that define them.
 * They are registered once at startup and cannot be replaced, so the imports are the same functions.
 * Handles `const { mapPropertyErrors } = Component.getComponentHelper();` and `Component.getComponentHelper().mapState`.
 *
 * @example
 * // const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
 * // → (removed; mapPropertyErrors is imported from ../dependencies)
 * // dependencies.ts: export { mapPropertyErrors } from 'src/app/service/map-errors.service';
 */
export const rewriteComponentHelper: UseRewrite = (use, context) => {
    const call = use.call;
    if (!call || call.arguments.length > 0 || !isPath(use, 'Component', 'getComponentHelper')) {
        return null;
    }

    const parent = call.parent;
    if (ts.isPropertyAccessExpression(parent) && parent.expression === call && !parent.questionDotToken) {
        const name = parent.name.text;
        const helper = context.index.findComponentHelper(name);

        return helper && context.isFree(name)
            ? replaceNode(parent, context, name, [{ kind: 'reexport', ...helper, exported: name }])
            : null;
    }

    return rewriteDestructure(call, context);
};

/** Removes `const { a, b: c } = <call>;` and imports each helper under the local name the file gave it. */
const rewriteDestructure = (call: ts.CallExpression, context: Parameters<UseRewrite>[1]): Rewrite | null => {
    const declaration = call.parent;
    const list = declaration.parent;
    const statement = list?.parent;
    if (
        !ts.isVariableDeclaration(declaration) ||
        !ts.isObjectBindingPattern(declaration.name) ||
        !ts.isVariableDeclarationList(list) ||
        list.declarations.length !== 1 ||
        !ts.isVariableStatement(statement) ||
        statement.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword)
    ) {
        return null;
    }

    const imports: Record<string, string> = {};
    const dependencies: Dependency[] = [];
    for (const element of declaration.name.elements) {
        if (element.dotDotDotToken || element.initializer || !ts.isIdentifier(element.name)) {
            return null;
        }

        const local = element.name.text;
        const name = element.propertyName && ts.isIdentifier(element.propertyName) ? element.propertyName.text : local;
        const helper = context.index.findComponentHelper(name);
        if (!helper || context.declared.get(local) !== 1) {
            return null;
        }

        imports[local] = name;
        dependencies.push({ kind: 'reexport', ...helper, exported: name });
    }

    return { ...removalRange(context.source, statement), text: '', imports, dependencies, outsideEdits: [] };
};

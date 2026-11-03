/**
 * @sw-package framework
 * @private
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

/** A module and export that hold a value, as an import names them; `module` is a package or a file path. */
type Export = { module: string; imported: string; file: string | null };

/**
 * What the resolver knows about a value: the export it can be imported from, if any, and what it is,
 * so a path can go on into an object, a module namespace, or stop at a function.
 */
type Value = {
    export: Export | null;
    /** Whether the value comes from a package, whose functions are plain module functions. */
    isPackage?: boolean;
    object?: { file: string; node: ts.ObjectLiteralExpression };
    namespace?: string;
    declaration?: ts.Node;
};

const OPAQUE: Value = { export: null };

const SCRIPT_FILES = [
    '',
    '.ts',
    '.js',
    '/index.ts',
    '/index.js',
];

/** Whether a function never reads `this`, so calling it without its object behaves the same. */
const ignoresThis = (declaration: ts.Node): boolean => {
    if (ts.isArrowFunction(declaration)) {
        return true;
    }

    if (
        !ts.isFunctionDeclaration(declaration) &&
        !ts.isFunctionExpression(declaration) &&
        !ts.isMethodDeclaration(declaration)
    ) {
        return false;
    }

    let readsThis = false;
    const visit = (node: ts.Node) => {
        if (node.kind === ts.SyntaxKind.ThisKeyword) {
            readsThis = true;
        } else if (!(ts.isFunctionLike(node) && !ts.isArrowFunction(node)) && !ts.isClassLike(node)) {
            node.forEachChild(visit);
        }
    };
    declaration.body?.forEachChild(visit);

    return !readsThis;
};

const unwrap = (expression: ts.Expression): ts.Expression =>
    ts.isParenthesizedExpression(expression) ||
    ts.isAsExpression(expression) ||
    ts.isSatisfiesExpression(expression) ||
    ts.isNonNullExpression(expression)
        ? unwrap(expression.expression)
        : expression;

const hasModifier = (node: ts.Node, kind: ts.SyntaxKind) =>
    ts.canHaveModifiers(node) && !!ts.getModifiers(node)?.some((modifier) => modifier.kind === kind);

const hasExportModifier = (node: ts.Node) => hasModifier(node, ts.SyntaxKind.ExportKeyword);

/**
 * Follows a path of the `Shopware` global from `src/core/shopware.ts` through the imports and object literals
 * of the files behind it, to the deepest export that holds the same value.
 * It reads the files with the TypeScript parser only; anything it cannot follow statically ends the path.
 *
 * @example
 * const resolve = createGlobalResolver(adminRoot);
 * resolve(['Utils', 'format', 'fileSize'], true);
 * // → { module: 'src/core/service/utils/format.utils', imported: 'fileSize', name: 'fileSize', consumed: 3 }
 */
export function createGlobalResolver(adminRoot: string) {
    const sources = new Map<string, ts.SourceFile>();
    const parse = (file: string) => {
        let source = sources.get(file);
        if (!source) {
            source = ts.createSourceFile(file, fs.readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true);
            sources.set(file, source);
        }

        return source;
    };

    const resolveFile = (fromFile: string, specifier: string): string | null => {
        const base = specifier.startsWith('src/')
            ? path.join(adminRoot, specifier)
            : specifier.startsWith('.')
              ? path.resolve(path.dirname(fromFile), specifier)
              : null;

        return base === null
            ? null
            : (SCRIPT_FILES.map((suffix) => `${base}${suffix}`).find(
                  (candidate) => fs.existsSync(candidate) && fs.statSync(candidate).isFile(),
              ) ?? null);
    };

    /** The module path an import writes for a file: `src/…` without the extension or a trailing `/index`. */
    const moduleId = (file: string) =>
        path
            .relative(adminRoot, file)
            .split(path.sep)
            .join('/')
            .replace(/\.[jt]s$/, '')
            .replace(/\/index$/, '');

    const exportValue = (file: string, name: string, depth: number): Value => {
        const own: Export = { module: moduleId(file), imported: name, file };

        for (const statement of parse(file).statements) {
            if (name === 'default' && ts.isExportAssignment(statement) && !statement.isExportEquals) {
                return { ...evaluate(file, statement.expression, depth), export: own };
            }

            const isDefault = hasModifier(statement, ts.SyntaxKind.DefaultKeyword);
            if (
                (ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement)) &&
                hasExportModifier(statement) &&
                (isDefault ? name === 'default' : statement.name?.text === name)
            ) {
                return { export: own, declaration: statement };
            }

            if (ts.isVariableStatement(statement) && hasExportModifier(statement)) {
                const declaration = statement.declarationList.declarations.find(
                    (candidate) => ts.isIdentifier(candidate.name) && candidate.name.text === name,
                );
                if (declaration?.initializer) {
                    return { ...evaluate(file, declaration.initializer, depth), export: own };
                }
            }

            if (
                ts.isExportDeclaration(statement) &&
                !statement.isTypeOnly &&
                statement.exportClause &&
                ts.isNamedExports(statement.exportClause)
            ) {
                const specifier = statement.exportClause.elements.find((element) => element.name.text === name);
                if (!specifier) {
                    continue;
                }

                const local = (specifier.propertyName ?? specifier.name).text;
                if (statement.moduleSpecifier && ts.isStringLiteral(statement.moduleSpecifier)) {
                    const target = resolveFile(file, statement.moduleSpecifier.text);
                    return { ...(target ? exportValue(target, local, depth + 1) : OPAQUE), export: own };
                }

                return { ...identifierValue(file, local, depth), export: own };
            }
        }

        return OPAQUE;
    };

    /** The value a name has at the top level of a file: an import, or a local declaration. */
    const identifierValue = (file: string, name: string, depth: number): Value => {
        if (depth > 20) {
            return OPAQUE;
        }

        for (const statement of parse(file).statements) {
            if (
                ts.isImportDeclaration(statement) &&
                ts.isStringLiteral(statement.moduleSpecifier) &&
                statement.importClause
            ) {
                const clause = statement.importClause;
                const specifier = statement.moduleSpecifier.text;
                const target = resolveFile(file, specifier);
                const fromPackage = (imported: string): Value => ({
                    export: { module: specifier, imported, file: null },
                    isPackage: true,
                });

                if (clause.name?.text === name) {
                    return target ? exportValue(target, 'default', depth + 1) : fromPackage('default');
                }

                const bindings = clause.namedBindings;
                if (bindings && ts.isNamespaceImport(bindings) && bindings.name.text === name) {
                    return target ? { export: null, namespace: target } : OPAQUE;
                }

                const element =
                    bindings && ts.isNamedImports(bindings)
                        ? bindings.elements.find((candidate) => candidate.name.text === name)
                        : undefined;
                if (element) {
                    const imported = (element.propertyName ?? element.name).text;
                    return target ? exportValue(target, imported, depth + 1) : fromPackage(imported);
                }
            }

            if ((ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement)) && statement.name?.text === name) {
                return { export: exportOfLocal(file, name), declaration: statement };
            }

            if (ts.isVariableStatement(statement)) {
                const declaration = statement.declarationList.declarations.find(
                    (candidate) => ts.isIdentifier(candidate.name) && candidate.name.text === name,
                );
                if (declaration?.initializer) {
                    const isConst = (statement.declarationList.flags & ts.NodeFlags.Const) !== 0;
                    return isConst
                        ? { ...evaluate(file, declaration.initializer, depth + 1), export: exportOfLocal(file, name) }
                        : OPAQUE;
                }
            }
        }

        return OPAQUE;
    };

    /** The export that holds a local declaration under its own name, if the file has one. */
    const exportOfLocal = (file: string, name: string): Export | null => {
        const isExported = parse(file).statements.some(
            (statement) =>
                (hasExportModifier(statement) &&
                    !hasModifier(statement, ts.SyntaxKind.DefaultKeyword) &&
                    (((ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement)) &&
                        statement.name?.text === name) ||
                        (ts.isVariableStatement(statement) &&
                            statement.declarationList.declarations.some(
                                (declaration) => ts.isIdentifier(declaration.name) && declaration.name.text === name,
                            )))) ||
                (ts.isExportDeclaration(statement) &&
                    !statement.moduleSpecifier &&
                    !statement.isTypeOnly &&
                    !!statement.exportClause &&
                    ts.isNamedExports(statement.exportClause) &&
                    statement.exportClause.elements.some((element) => !element.propertyName && element.name.text === name)),
        );

        return isExported ? { module: moduleId(file), imported: name, file } : null;
    };

    const evaluate = (file: string, expression: ts.Expression, depth: number): Value => {
        const node = unwrap(expression);

        if (ts.isIdentifier(node)) {
            return identifierValue(file, node.text, depth + 1);
        }

        if (ts.isObjectLiteralExpression(node)) {
            return { export: null, object: { file, node } };
        }

        if (ts.isPropertyAccessExpression(node) && ts.isIdentifier(node.name)) {
            return member(evaluate(file, node.expression, depth + 1), node.name.text, depth + 1);
        }

        if (ts.isFunctionExpression(node) || ts.isArrowFunction(node) || ts.isClassExpression(node)) {
            return { export: null, declaration: node };
        }

        return OPAQUE;
    };

    const member = (value: Value, name: string, depth: number): Value => {
        if (value.namespace) {
            return exportValue(value.namespace, name, depth + 1);
        }

        if (!value.object) {
            return OPAQUE;
        }

        const { file, node } = value.object;
        const property = node.properties.find(
            (candidate) =>
                !ts.isSpreadAssignment(candidate) &&
                candidate.name !== undefined &&
                (ts.isIdentifier(candidate.name) || ts.isStringLiteral(candidate.name)) &&
                candidate.name.text === name,
        );

        if (property && ts.isPropertyAssignment(property)) {
            return evaluate(file, property.initializer, depth + 1);
        }

        if (property && ts.isShorthandPropertyAssignment(property)) {
            return identifierValue(file, name, depth + 1);
        }

        if (property && ts.isMethodDeclaration(property)) {
            return { export: null, declaration: property };
        }

        return OPAQUE;
    };

    const shopware = path.join(adminRoot, 'src/core/shopware.ts');
    const rootValue = (name: string): Value => {
        const shopwareClass = parse(shopware).statements.find(
            (statement): statement is ts.ClassDeclaration =>
                ts.isClassDeclaration(statement) && statement.name?.text === 'ShopwareClass',
        );
        const field = shopwareClass?.members.find(
            (candidate): candidate is ts.PropertyDeclaration =>
                ts.isPropertyDeclaration(candidate) &&
                ts.isIdentifier(candidate.name) &&
                candidate.name.text === name &&
                !candidate.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.StaticKeyword),
        );

        return field?.initializer ? evaluate(shopware, field.initializer, 0) : OPAQUE;
    };

    return (globalPath: string[], isCallee: boolean) => {
        const checkpoints: { export: Export; consumed: number; name: string; value: Value }[] = [];
        let value = rootValue(globalPath[0]);

        for (let index = 0; ; index += 1) {
            if (value.export) {
                checkpoints.push({ export: value.export, consumed: index + 1, name: globalPath[index], value });
            }

            if (index + 1 >= globalPath.length || (!value.object && !value.namespace)) {
                break;
            }

            value = member(value, globalPath[index + 1], 0);
        }

        const usable = checkpoints.filter(({ consumed, value: { declaration, isPackage } }) => {
            if (!isCallee || consumed < globalPath.length) {
                return true;
            }

            // A function from a package is a plain module function; one of ours must not read `this`.
            return isPackage || (declaration !== undefined && ignoresThis(declaration));
        });
        const deepest = usable[usable.length - 1];

        return deepest
            ? {
                  module: deepest.export.module,
                  imported: deepest.export.imported,
                  name: deepest.name,
                  consumed: deepest.consumed,
              }
            : null;
    };
}

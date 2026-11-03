/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

/**
 * How a component reaches the component registry today, and where in its file that happens.
 * `start` and `end` span the whole statement with its doc comment and line break, so cutting them out leaves no gap.
 * `specifier` is the path the lazy `import()` loads, as written.
 * `isPrivate` tells whether the doc comment of the statement marks the component `@private`.
 */
export type Registration = {
    start: number;
    end: number;
    specifier: string;
    isPrivate: boolean;
} & ({ kind: 'register' } | { kind: 'extend'; base: string });

const CALLEES: Record<string, Registration['kind']> = {
    'Shopware.Component.register': 'register',
    'Component.register': 'register',
    'Shopware.Component.extend': 'extend',
    'Component.extend': 'extend',
};

/** The path of `() => import('<path>')`, or null for any other loader. */
const readLazyImport = (loader: ts.Expression | undefined): string | null => {
    if (!loader || !ts.isArrowFunction(loader) || loader.parameters.length > 0 || !ts.isCallExpression(loader.body)) {
        return null;
    }

    const [specifier] = loader.body.arguments;
    return loader.body.expression.kind === ts.SyntaxKind.ImportKeyword && specifier && ts.isStringLiteral(specifier)
        ? specifier.text
        : null;
};

/** The range of a statement from its own line, including its doc comment, up to and including its line break. */
const statementRange = (source: ts.SourceFile, statement: ts.Statement) => {
    const text = source.text;
    const lineStart = text.lastIndexOf('\n', statement.getStart(source, true) - 1) + 1;
    const lineBreak = text.indexOf('\n', statement.getEnd());

    return { start: lineStart, end: lineBreak === -1 ? text.length : lineBreak + 1 };
};

/** Every expression statement of a file, also those inside functions, such as a registration inside `export default () => {}`. */
const expressionStatements = (node: ts.Node): ts.ExpressionStatement[] =>
    ts.isExpressionStatement(node) ? [node] : node.getChildren().flatMap((child) => expressionStatements(child));

/**
 * Finds the statement that registers or extends the named component with a lazy import, written on
 * one line or spread over several.
 * Returns null when the file has none, for example when a parent imports the component directly.
 *
 * @example
 * findRegistration("Shopware.Component.extend('sw-b', 'sw-a', () => import('./b'));\n", 'sw-b');
 * // → { kind: 'extend', base: 'sw-a', specifier: './b', isPrivate: false, start: 0, end: 63 }
 */
export function findRegistration(code: string, name: string): Registration | null {
    const source = ts.createSourceFile('registration.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);

    for (const statement of expressionStatements(source)) {
        if (!ts.isCallExpression(statement.expression)) {
            continue;
        }

        const call = statement.expression;
        const kind = CALLEES[call.expression.getText(source)];
        const [componentName, ...rest] = call.arguments;
        if (!kind || !componentName || !ts.isStringLiteral(componentName) || componentName.text !== name) {
            continue;
        }

        const base = kind === 'extend' ? rest.shift() : undefined;
        const specifier = readLazyImport(rest[0]);
        if (!specifier || (kind === 'extend' && !(base && ts.isStringLiteral(base)))) {
            continue;
        }

        const range = statementRange(source, statement);
        const isPrivate = /@private\b/.test(source.text.slice(range.start, statement.getStart(source)));
        return kind === 'extend'
            ? { kind, base: (base as ts.StringLiteral).text, specifier, isPrivate, ...range }
            : { kind, specifier, isPrivate, ...range };
    }

    return null;
}

/**
 * Finds the static imports of a file whose path `matches` accepts, so a caller can point them somewhere else.
 * `start` and `end` span the path inside its quotes, `lineStart` is where the import's line begins.
 *
 * @example
 * findImports("import Card from '../card';", (specifier) => specifier === '../card');
 * // → [{ specifier: '../card', start: 18, end: 25, lineStart: 0 }]
 */
export function findImports(
    code: string,
    matches: (specifier: string) => boolean,
): { specifier: string; start: number; end: number; lineStart: number }[] {
    const source = ts.createSourceFile('importer.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);

    return source.statements.flatMap((statement) => {
        if (!ts.isImportDeclaration(statement) || !ts.isStringLiteral(statement.moduleSpecifier)) {
            return [];
        }

        const literal = statement.moduleSpecifier;
        if (!matches(literal.text)) {
            return [];
        }

        return [
            {
                specifier: literal.text,
                start: literal.getStart(source) + 1,
                end: literal.getEnd() - 1,
                lineStart: statementRange(source, statement).start,
            },
        ];
    });
}

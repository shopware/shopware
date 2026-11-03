/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

/**
 * How a component reaches the component registry today, and where in its file that happens.
 * `start` and `end` span the whole statement with the comments right above it and its line break, so cutting them out leaves no gap.
 * `specifier` is the path the lazy `import()` loads, as written.
 * `isPrivate` tells whether the doc comment of the statement marks the component `@private`.
 * `isConditional` tells whether the statement runs only under a condition, such as inside an `if` on a feature flag.
 */
export type Registration = {
    start: number;
    end: number;
    specifier: string;
    isPrivate: boolean;
    isConditional: boolean;
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

/** Whether a comment switches a lint rule on or off for the lines below it, so it belongs to no single statement. */
const isLintBlock = (comment: string) => /^\/\*\s*eslint-(disable|enable)(?![-\w])/.test(comment);

/**
 * Where the comments right above a statement begin: its doc comment and an `eslint-disable-next-line`,
 * up to a blank line or an `eslint-disable` block, which stay because they belong to the file or to several statements.
 */
const attachedCommentsStart = (text: string, statement: ts.Statement, source: ts.SourceFile) => {
    let start = statement.getStart(source);
    const comments = ts.getLeadingCommentRanges(text, statement.getFullStart()) ?? [];
    for (const comment of [...comments].reverse()) {
        const between = text.slice(comment.end, start);
        if (/\n[ \t]*\n/.test(between) || !between.includes('\n') || isLintBlock(text.slice(comment.pos, comment.end))) {
            break;
        }

        start = comment.pos;
    }

    return start;
};

/** The range of a statement from its own line, including the comments right above it, up to and including its line break. */
const statementRange = (source: ts.SourceFile, statement: ts.Statement) => {
    const text = source.text;
    const lineStart = text.lastIndexOf('\n', attachedCommentsStart(text, statement, source) - 1) + 1;
    const lineBreak = text.indexOf('\n', statement.getEnd());

    return { start: lineStart, end: lineBreak === -1 ? text.length : lineBreak + 1 };
};

/**
 * Whether a statement runs only under a condition: inside an `if`, a loop, a `switch`, a `try` or a function other than
 * the `export default () => {}` that wraps the registrations of `src/app/component/index.ts`.
 */
const isConditional = (statement: ts.Node) => {
    for (let node = statement.parent; !ts.isSourceFile(node); node = node.parent) {
        const isExportedWrapper =
            ts.isExportAssignment(node) || (ts.isArrowFunction(node) && ts.isExportAssignment(node.parent));
        const isPlainBlock = ts.isBlock(node) && (ts.isSourceFile(node.parent) || ts.isArrowFunction(node.parent));
        if (!isExportedWrapper && !isPlainBlock) {
            return true;
        }
    }

    return false;
};

/** Every expression statement of a file, also those inside functions, such as a registration inside `export default () => {}`. */
const expressionStatements = (node: ts.Node): ts.ExpressionStatement[] =>
    ts.isExpressionStatement(node) ? [node] : node.getChildren().flatMap((child) => expressionStatements(child));

/** Every statement of a file that registers or extends a component by its name with a lazy import, with that name. */
function findRegistrations(code: string): (Registration & { name: string })[] {
    const source = ts.createSourceFile('registration.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);

    return expressionStatements(source).flatMap((statement) => {
        if (!ts.isCallExpression(statement.expression)) {
            return [];
        }

        const call = statement.expression;
        const kind = CALLEES[call.expression.getText(source)];
        const [componentName, ...rest] = call.arguments;
        if (!kind || !componentName || !ts.isStringLiteral(componentName)) {
            return [];
        }

        const base = kind === 'extend' ? rest.shift() : undefined;
        const specifier = readLazyImport(rest[0]);
        if (!specifier || (kind === 'extend' && !(base && ts.isStringLiteral(base)))) {
            return [];
        }

        const range = statementRange(source, statement);
        const docStart = Math.min(range.start, statement.getStart(source, true));
        const isPrivate = /@private\b/.test(source.text.slice(docStart, statement.getStart(source)));
        const name = componentName.text;
        return [
            kind === 'extend'
                ? {
                      kind,
                      base: (base as ts.StringLiteral).text,
                      specifier,
                      isPrivate,
                      isConditional: isConditional(statement),
                      name,
                      ...range,
                  }
                : { kind, specifier, isPrivate, isConditional: isConditional(statement), name, ...range },
        ];
    });
}

const withoutName = ({ name, ...registration }: Registration & { name: string }): Registration => registration;

/**
 * Finds the statement that registers or extends the named component with a lazy import, written on
 * one line or spread over several.
 * Returns null when the file has none, for example when a parent imports the component directly.
 *
 * @example
 * findRegistration("Shopware.Component.extend('sw-b', 'sw-a', () => import('./b'));\n", 'sw-b');
 * // → { kind: 'extend', base: 'sw-a', specifier: './b', isPrivate: false, isConditional: false, start: 0, end: 63 }
 */
export function findRegistration(code: string, name: string): Registration | null {
    const found = findRegistrations(code).find((registration) => registration.name === name);
    return found ? withoutName(found) : null;
}

/**
 * Finds the statement whose lazy import `leadsHere` accepts, and the name it registers the component under,
 * for a directory not named after its component, such as the `component/` of a CMS element.
 *
 * @example
 * findRegistrationByPath("Shopware.Component.register('sw-cms-el-text', () => import('./component'));\n", (s) => s === './component');
 * // → { name: 'sw-cms-el-text', registration: { kind: 'register', specifier: './component', … } }
 */
export function findRegistrationByPath(
    code: string,
    leadsHere: (specifier: string) => boolean,
): { name: string; registration: Registration } | null {
    const found = findRegistrations(code).find((registration) => leadsHere(registration.specifier));
    return found ? { name: found.name, registration: withoutName(found) } : null;
}

/**
 * Finds the static imports and `export … from` re-exports of a file whose path `matches` accepts,
 * so a caller can point them somewhere else.
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
        const isImportOrReexport = ts.isImportDeclaration(statement) || ts.isExportDeclaration(statement);
        if (!isImportOrReexport || !statement.moduleSpecifier || !ts.isStringLiteral(statement.moduleSpecifier)) {
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
                lineStart: source.text.lastIndexOf('\n', statement.getStart(source) - 1) + 1,
            },
        ];
    });
}

/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

const parse = (code: string) => ts.createSourceFile('file.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);

/** The range of a statement with the doc comment right above it and its line, to cut it out. */
const fullLineRange = (source: ts.SourceFile, statement: ts.Statement) => {
    const text = source.text;
    const comments = ts.getLeadingCommentRanges(text, statement.getFullStart()) ?? [];
    const start = text.lastIndexOf('\n', (comments[comments.length - 1]?.pos ?? statement.getStart(source)) - 1) + 1;
    const lineBreak = text.indexOf('\n', statement.getEnd());

    return { start, end: lineBreak === -1 ? text.length : lineBreak + 1 };
};

/**
 * Removes the re-exports of a nested component from a parent's dependencies.ts, and returns the names they
 * gave it. Returns null when a re-export does anything but `export { default as X } from '<path>'`.
 *
 * @example
 * dropComponentReexport("/** @private *\/\nexport { default as SwCard } from '../../component/sw-card';\n", (s) => s.endsWith('sw-card'));
 * // → { code: '', names: ['SwCard'] }
 */
export function dropComponentReexport(
    code: string,
    leadsToComponent: (specifier: string) => boolean,
): { code: string; names: string[] } | null {
    const source = parse(code);
    const names: string[] = [];
    const edits: { start: number; end: number; text: string }[] = [];

    for (const statement of source.statements) {
        if (
            !ts.isExportDeclaration(statement) ||
            !statement.moduleSpecifier ||
            !ts.isStringLiteral(statement.moduleSpecifier) ||
            !leadsToComponent(statement.moduleSpecifier.text)
        ) {
            continue;
        }

        const clause = statement.exportClause;
        if (statement.isTypeOnly || !clause || !ts.isNamedExports(clause)) {
            return null;
        }

        const isDefault = (element: ts.ExportSpecifier) => element.propertyName?.text === 'default' && !element.isTypeOnly;
        if (!clause.elements.every(isDefault)) {
            return null;
        }

        names.push(...clause.elements.map((element) => element.name.text));
        edits.push({ ...fullLineRange(source, statement), text: '' });
    }

    const rewritten = edits.reduceRight((text, { start, end }) => `${text.slice(0, start)}${text.slice(end)}`, code);
    return { code: rewritten, names };
}

/** Adds `export { defineAsyncComponent } from 'vue';` to a dependencies.ts that does not export it yet. */
export function exportDefineAsyncComponent(code: string): string {
    const source = parse(code);
    const fromVue = source.statements.find(
        (statement): statement is ts.ExportDeclaration =>
            ts.isExportDeclaration(statement) &&
            !statement.isTypeOnly &&
            !!statement.moduleSpecifier &&
            ts.isStringLiteral(statement.moduleSpecifier) &&
            statement.moduleSpecifier.text === 'vue' &&
            !!statement.exportClause &&
            ts.isNamedExports(statement.exportClause),
    );

    if (fromVue?.exportClause && ts.isNamedExports(fromVue.exportClause)) {
        const elements = fromVue.exportClause.elements.map((element) => element.getText(source));
        if (elements.includes('defineAsyncComponent')) {
            return code;
        }

        const clause = fromVue.exportClause;
        return `${code.slice(0, clause.getStart(source))}{ ${['defineAsyncComponent', ...elements].join(', ')} }${code.slice(clause.getEnd())}`;
    }

    const statement = "/** @private */\nexport { defineAsyncComponent } from 'vue';\n";
    const firstExport = source.statements.find(
        (candidate) => ts.isExportDeclaration(candidate) || ts.isVariableStatement(candidate),
    );
    if (!firstExport) {
        return `${code.replace(/\n*$/, '\n')}\n${statement}`;
    }

    const at = fullLineRange(source, firstExport).start;
    return `${code.slice(0, at)}${statement}${code.slice(at)}`;
}

/**
 * Mounts a nested component in a file of the parent's `src/` from its private.ts: the file imports the declaration
 * instead of the name from `../dependencies`, and lists it in `components` as `defineAsyncComponent(X.load)`.
 * Returns the code unchanged when the file does not import the name, and null when it uses the name anywhere
 * but in `components`, where the declaration would not behave like the component.
 *
 * @example
 * mountNested("import { SwCard } from '../dependencies';\nexport default { components: { SwCard } };\n", 'SwCard', './sw-card/private');
 * // → "import { defineAsyncComponent } from '../dependencies';\nimport SwCard from './sw-card/private';\n…{ SwCard: defineAsyncComponent(SwCard.load) }…"
 */
export function mountNested(code: string, name: string, privateSpecifier: string): string | null {
    const source = parse(code);
    const declaration = source.statements.find(
        (statement): statement is ts.ImportDeclaration =>
            ts.isImportDeclaration(statement) &&
            ts.isStringLiteral(statement.moduleSpecifier) &&
            /(^|\/)dependencies$/.test(statement.moduleSpecifier.text) &&
            !!statement.importClause?.namedBindings &&
            ts.isNamedImports(statement.importClause.namedBindings) &&
            statement.importClause.namedBindings.elements.some((element) => element.name.text === name),
    );
    if (!declaration) {
        return code;
    }

    const edits: { start: number; end: number; text: string }[] = [];
    let isSafe = true;
    const visit = (node: ts.Node) => {
        if (ts.isIdentifier(node) && node.text === name && !ts.isImportSpecifier(node.parent)) {
            const property = node.parent;
            const isComponentsEntry =
                (ts.isShorthandPropertyAssignment(property) ||
                    (ts.isPropertyAssignment(property) && property.initializer === node)) &&
                ts.isObjectLiteralExpression(property.parent) &&
                ts.isPropertyAssignment(property.parent.parent) &&
                ts.isIdentifier(property.parent.parent.name) &&
                property.parent.parent.name.text === 'components';

            if (!isComponentsEntry) {
                isSafe = false;
            } else if (ts.isShorthandPropertyAssignment(property)) {
                edits.push({
                    start: node.getStart(source),
                    end: node.getEnd(),
                    text: `${name}: defineAsyncComponent(${name}.load)`,
                });
            } else {
                edits.push({ start: node.getStart(source), end: node.getEnd(), text: `defineAsyncComponent(${name}.load)` });
            }
        }

        node.forEachChild(visit);
    };
    visit(source);

    if (!isSafe) {
        return null;
    }

    const bindings = declaration.importClause!.namedBindings as ts.NamedImports;
    const kept = bindings.elements.map((element) => element.getText(source)).filter((text) => text !== name);
    const names = kept.includes('defineAsyncComponent') ? kept : ['defineAsyncComponent', ...kept];
    edits.push({
        start: bindings.getStart(source),
        end: bindings.getEnd(),
        text: `{ ${names.join(', ')} }`,
    });
    const lineEnd = code.indexOf('\n', declaration.getEnd()) + 1;
    edits.push({ start: lineEnd, end: lineEnd, text: `import ${name} from '${privateSpecifier}';\n` });

    return [...edits]
        .sort((a, b) => b.start - a.start)
        .reduce(
            (text, { start, end, text: replacement }) => `${text.slice(0, start)}${replacement}${text.slice(end)}`,
            code,
        );
}

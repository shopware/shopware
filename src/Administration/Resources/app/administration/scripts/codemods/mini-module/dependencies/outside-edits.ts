/**
 * @sw-package framework
 * @private
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import type { OutsideEdit } from './model';
import { findRegisterCall } from './registrations';

const parse = (file: string, code: string) => ts.createSourceFile(file, code, ts.ScriptTarget.Latest, true);

/** Where a statement begins, including its doc comment, so a new statement goes above both. */
const lineStartBefore = (source: ts.SourceFile, statement: ts.Statement) => {
    const comments = ts.getLeadingCommentRanges(source.text, statement.getFullStart()) ?? [];
    const start = comments[0]?.pos ?? statement.getStart(source);
    return source.text.lastIndexOf('\n', start - 1) + 1;
};

/**
 * Adds the constant of a service name to src/app/service-names, before the first constant that sorts after it,
 * and exports it from its index.ts.
 *
 * @example
 * addServiceName(code, 'acl');
 * // → "… export const acl = 'acl'; …"
 */
export function addServiceName(code: string, name: string): string {
    const source = parse('service-names.ts', code);
    const constant = `/**
 * The name the ${name} service is registered and injected under.
 *
 * @example
 * export default { inject: [${name}] };
 *
 * @public
 */
export const ${name} = '${name}';
`;
    const next = source.statements.find(
        (statement) =>
            ts.isVariableStatement(statement) &&
            statement.declarationList.declarations.some(
                (declaration) => ts.isIdentifier(declaration.name) && declaration.name.text.localeCompare(name) > 0,
            ),
    );

    if (!next) {
        return `${code.replace(/\n*$/, '\n')}\n${constant}`;
    }

    const at = lineStartBefore(source, next);
    return `${code.slice(0, at)}${constant}\n${code.slice(at)}`;
}

/** Adds names to the `export { … } from './src/service-names';` of src/app/service-names/index.ts, sorted. */
export function exportServiceNames(code: string, names: string[]): string {
    const source = parse('index.ts', code);
    const statement = source.statements.find(
        (candidate): candidate is ts.ExportDeclaration =>
            ts.isExportDeclaration(candidate) && !!candidate.exportClause && ts.isNamedExports(candidate.exportClause),
    );
    if (!statement?.exportClause || !ts.isNamedExports(statement.exportClause)) {
        throw new Error('src/app/service-names/index.ts has no export list to add the service names to.');
    }

    const all = [...new Set([...statement.exportClause.elements.map((element) => element.name.text), ...names])].sort(
        (a, b) => a.localeCompare(b),
    );
    const clause = statement.exportClause;
    return `${code.slice(0, clause.getStart(source))}{ ${all.join(', ')} }${code.slice(clause.getEnd())}`;
}

/**
 * Exports the string a store or filter is registered under from the file that registers it,
 * and registers it through that constant, so code can import the name instead of writing it.
 *
 * @example
 * exportRegisteredName(code, 'Filter', 'date', 'dateFilterName');
 * // Shopware.Filter.register('date', …) → export const dateFilterName = 'date'; Shopware.Filter.register(dateFilterName, …)
 */
export function exportRegisteredName(code: string, kind: 'Store' | 'Filter', name: string, exportName: string): string {
    const source = parse('registry.ts', code);
    const call = findRegisterCall(source, kind, name);
    if (!call?.literal) {
        throw new Error(`Found no ${kind}.register('${name}') to export the name of.`);
    }

    const description =
        kind === 'Store'
            ? 'The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.'
            : `The name the ${name} filter is registered under, for code that looks it up with \`Shopware.Filter.getByName()\`.`;
    const constant = `/**
 * @private
 * ${description}
 */
export const ${exportName} = '${name}';

`;
    const at = lineStartBefore(source, call.statement);
    const literalStart = call.literal.getStart(source);

    return `${code.slice(0, at)}${constant}${code.slice(at, literalStart)}${exportName}${code.slice(call.literal.getEnd())}`;
}

/**
 * Applies the edits outside the module that the rewrites asked for, each once, and returns the changed files
 * relative to the Administration root.
 */
export function applyOutsideEdits(adminRoot: string, edits: OutsideEdit[]): string[] {
    const unique = [...new Map(edits.map((edit) => [JSON.stringify(edit), edit])).values()];
    const changed = new Set<string>();
    const update = (file: string, change: (code: string) => string) => {
        fs.writeFileSync(file, change(fs.readFileSync(file, 'utf8')));
        changed.add(path.relative(adminRoot, file).split(path.sep).join('/'));
    };

    const serviceNames = unique.flatMap((edit) => (edit.kind === 'service-name' ? [edit.name] : []));
    if (serviceNames.length > 0) {
        const directory = path.join(adminRoot, 'src/app/service-names');
        update(path.join(directory, 'src/service-names.ts'), (code) => serviceNames.reduce(addServiceName, code));
        update(path.join(directory, 'index.ts'), (code) => exportServiceNames(code, serviceNames));
    }

    unique.forEach((edit) => {
        if (edit.kind === 'store-id') {
            update(edit.file, (code) => exportRegisteredName(code, 'Store', edit.id, edit.exportName));
        } else if (edit.kind === 'filter-name') {
            update(edit.file, (code) => exportRegisteredName(code, 'Filter', edit.name, edit.exportName));
        }
    });

    return [...changed];
}

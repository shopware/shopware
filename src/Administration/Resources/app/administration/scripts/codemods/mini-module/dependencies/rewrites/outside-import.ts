/**
 * @sw-package framework
 * @private
 */

import path from 'node:path';
import ts from 'typescript';
import type { FileRewrite, Reexport, Rewrite, RewriteContext } from '../model';
import { removalRange } from './helpers';

/** Imports that only add a template or styles to the bundle, which `src/` keeps. */
const ASSET_IMPORT = /\.(s?css|twig)$/;

/** Whether an import stays in `src/`: an asset, `../dependencies`, or a file of the module's own `src/`. */
export const staysInside = (
    specifier: string,
    context: Pick<RewriteContext, 'fileName' | 'moduleRoot' | 'dependenciesFile'>,
) => {
    if (ASSET_IMPORT.test(specifier)) {
        return true;
    }

    if (!specifier.startsWith('.')) {
        return false;
    }

    const target = path.resolve(path.dirname(context.fileName), specifier);
    return (
        target === context.dependenciesFile.replace(/\.ts$/, '') ||
        target.startsWith(`${path.join(context.moduleRoot, 'src')}${path.sep}`)
    );
};

/** The path dependencies.ts writes for what the file imported: relative paths start from dependencies.ts. */
const fromDependencies = (specifier: string, context: RewriteContext) => {
    if (!specifier.startsWith('.')) {
        return specifier;
    }

    const target = path.resolve(path.dirname(context.fileName), specifier);
    const relativePath = path.relative(path.dirname(context.dependenciesFile), target).split(path.sep).join('/');

    return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
};

/** The names an import binds, as re-exports of dependencies.ts under the same local names. */
const readBindings = (statement: ts.ImportDeclaration, module: string): Reexport[] | null => {
    const clause = statement.importClause;
    if (!clause) {
        return null;
    }

    const typeOnly = clause.isTypeOnly;
    const reexports: Reexport[] = [];
    if (clause.name) {
        reexports.push({ kind: 'reexport', module, imported: 'default', exported: clause.name.text, typeOnly });
    }

    const bindings = clause.namedBindings;
    if (bindings && ts.isNamespaceImport(bindings)) {
        if (typeOnly) {
            return null;
        }

        reexports.push({ kind: 'reexport', module, imported: '*', exported: bindings.name.text });
    } else if (bindings) {
        bindings.elements.forEach((element) => {
            reexports.push({
                kind: 'reexport',
                module,
                imported: (element.propertyName ?? element.name).text,
                exported: element.name.text,
                typeOnly: typeOnly || element.isTypeOnly,
            });
        });
    }

    return reexports;
};

/**
 * Whether an import runs a module only for its side effects, such as `import 'flatpickr/dist/l10n';`,
 * apart from the template and styles a component imports.
 */
export const isSideEffectImport = (statement: ts.Statement): statement is ts.ImportDeclaration =>
    ts.isImportDeclaration(statement) &&
    !statement.importClause &&
    ts.isStringLiteral(statement.moduleSpecifier) &&
    !ASSET_IMPORT.test(statement.moduleSpecifier.text);

/**
 * An import from outside the module moves into dependencies.ts as a re-export under the same name,
 * so `src/` keeps its code and imports the name from `../dependencies`.
 * An import only for its side effects stays for the sweep, because dependencies.ts has none,
 * and so does every import above it: the side effect may need them loaded first, as an Ace mode needs Ace.
 *
 * @example
 * // import { mapState } from 'pinia';
 * // → import { mapState } from '../dependencies';
 * // dependencies.ts: export { mapState } from 'pinia';
 */
export const rewriteOutsideImports: FileRewrite = (context) => {
    const lastSideEffect = context.source.statements.findLastIndex(isSideEffectImport);

    return context.source.statements.slice(lastSideEffect + 1).flatMap((statement): Rewrite[] => {
        if (!ts.isImportDeclaration(statement) || !ts.isStringLiteral(statement.moduleSpecifier)) {
            return [];
        }

        const specifier = statement.moduleSpecifier.text;
        const reexports = staysInside(specifier, context)
            ? null
            : readBindings(statement, fromDependencies(specifier, context));
        if (!reexports || reexports.length === 0) {
            return [];
        }

        return [
            {
                ...removalRange(context.source, statement),
                text: '',
                imports: Object.fromEntries(reexports.map(({ exported }) => [exported, exported])),
                dependencies: reexports,
                outsideEdits: [],
            },
        ];
    });
};

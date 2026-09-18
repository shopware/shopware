/**
 * @sw-package framework
 *
 * The files the Administration evaluates before `src/index.ts` assigns `window.Shopware`.
 *
 * A `shopware:*` import in one of them throws at module evaluation, because the generated module reads
 * the global as soon as it is imported. The closure is the static import graph of `src/index.ts` and
 * `src/core/shopware.ts`; dynamic `import()` is deliberately not followed, since those resolve after boot.
 */

import fs from 'node:fs';
import path from 'node:path';
import { globSync } from 'glob';
import ts from 'typescript';

const CANDIDATE_SUFFIXES = [
    '',
    '.ts',
    '.js',
    '.vue',
    '.mjs',
    '/index.ts',
    '/index.js',
];

function resolveSpecifier(specifier: string, fromFile: string, srcDir: string): string | undefined {
    let base: string;

    if (specifier.startsWith('src/')) {
        base = path.join(srcDir, specifier.slice('src/'.length));
    } else if (specifier.startsWith('./') || specifier.startsWith('../')) {
        base = path.resolve(path.dirname(fromFile), specifier);
    } else {
        return undefined;
    }

    return CANDIDATE_SUFFIXES.map((suffix) => base + suffix).find(
        (candidate) => fs.existsSync(candidate) && fs.statSync(candidate).isFile(),
    );
}

/**
 * The module specifiers a file imports or re-exports at load time.
 *
 * Read from the AST rather than by pattern: an import list wrapped over several lines is the normal shape
 * here, and missing one hides everything it reaches. A dynamic `import()` is deliberately not reported,
 * because those resolve after boot.
 */
function staticSpecifiers(sourceFile: ts.SourceFile): string[] {
    const specifiers: string[] = [];

    sourceFile.statements.forEach((statement) => {
        if (!ts.isImportDeclaration(statement) && !ts.isExportDeclaration(statement)) {
            return;
        }

        const specifier = statement.moduleSpecifier;

        if (specifier && ts.isStringLiteral(specifier)) {
            specifiers.push(specifier.text);
        }
    });

    return specifiers;
}

function stringLiterals(node: ts.Expression): string[] {
    if (ts.isStringLiteral(node)) {
        return [node.text];
    }

    if (ts.isArrayLiteralExpression(node)) {
        return node.elements.filter(ts.isStringLiteral).map((element) => element.text);
    }

    return [];
}

/**
 * The files an `import.meta.glob(..., { eager: true })` pulls in.
 *
 * Vite compiles an eager glob into plain static imports, so its matches are evaluated exactly like a
 * written-out import. A glob without `eager` returns loader functions instead and resolves after boot,
 * which is why the flag decides.
 */
function eagerGlobMatches(sourceFile: ts.SourceFile, fromFile: string): string[] {
    const matches: string[] = [];

    const visit = (node: ts.Node): void => {
        if (
            ts.isCallExpression(node) &&
            ts.isPropertyAccessExpression(node.expression) &&
            node.expression.name.text === 'glob'
        ) {
            const [
                patterns,
                options,
            ] = node.arguments;

            const isEager =
                options &&
                ts.isObjectLiteralExpression(options) &&
                options.properties.some(
                    (property) =>
                        ts.isPropertyAssignment(property) &&
                        property.name.getText() === 'eager' &&
                        property.initializer.kind === ts.SyntaxKind.TrueKeyword,
                );

            if (patterns && isEager) {
                matches.push(...globSync(stringLiterals(patterns), { cwd: path.dirname(fromFile), absolute: true }));
            }
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    return matches;
}

/**
 * Absolute paths of every file evaluated before the global exists.
 *
 * @param srcDir absolute path of the Administration's `src` directory
 */
export function bootstrapClosure(srcDir: string): Set<string> {
    const reached = new Set<string>();

    const walk = (file: string): void => {
        if (reached.has(file)) {
            return;
        }

        reached.add(file);

        let text: string;

        try {
            text = fs.readFileSync(file, 'utf8');
        } catch {
            return;
        }

        const sourceFile = ts.createSourceFile(file, text, ts.ScriptTarget.Latest, true);

        staticSpecifiers(sourceFile).forEach((specifier) => {
            const target = resolveSpecifier(specifier, file, srcDir);

            if (target) {
                walk(target);
            }
        });

        eagerGlobMatches(sourceFile, file).forEach(walk);
    };

    [
        'index.ts',
        'core/shopware.ts',
    ].forEach((entry) => walk(path.join(srcDir, entry)));

    return reached;
}

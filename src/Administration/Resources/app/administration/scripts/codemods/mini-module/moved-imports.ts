/**
 * @sw-package framework
 * @private
 */

import path from 'node:path';

const { posix } = path;

/**
 * An import path in `from`, a bare import, `import()`, `require()` or `jest.mock()`: relative or through the
 * `src/…` alias, in quotes, or as the static head of a template literal up to its first `${`.
 */
const SPECIFIER =
    /((?:\bfrom|\bimport\(|\bimport|\brequire\(|\bjest\.mock\()\s*)(?:'((?:\.\.?\/|src\/)[^']*)'|`((?:\.\.?\/|src\/)[^`$]*))/g;

const SCRIPT_EXTENSION = /\.[jt]s$/;

/** The component whose entry other files import, for `rewriteMovedImports()` in a file outside its module. */
export type MovedEntry = { directory: string; name: string; isPrivate: boolean };

/**
 * The import paths a file writes, without their queries, as `findImports()` would see them, also in specs:
 * `import()`, `require()`, `jest.mock()` and the static head of a template literal.
 *
 * @example
 * findSpecifiers("jest.mock('src/a/b');\nimport x from '../c?raw';\n");
 * // → ['src/a/b', '../c']
 */
export function findSpecifiers(source: string): string[] {
    return [...source.matchAll(SPECIFIER)].map(
        ([
            ,
            ,
            quoted,
            template,
        ]) => (quoted ?? template).split('?')[0],
    );
}

/**
 * The text an import of the file has to contain, to search for its importers:
 * its name without the extension, or the name of its directory for an index file, which imports leave out.
 *
 * @example
 * importedName('src/a/subcomponents/sw-check-list/index.ts');
 * // → 'sw-check-list'
 */
export function importedName(file: string): string {
    const name = posix.basename(file).replace(SCRIPT_EXTENSION, '');
    return name === 'index' ? posix.basename(posix.dirname(file)) : name;
}

/**
 * Where a path relative to the Administration root ends up after the moves, or itself when it did not move.
 * It tries the extensions and `index` files an import may leave out.
 *
 * @example
 * movedPath(new Map([['src/a/index.js', 'src/a/src/a.js']]), 'src/a');
 * // → 'src/a/src/a.js'
 */
export function movedPath(moves: Map<string, string>, target: string): string {
    const candidates = [
        target,
        ...[
            '.js',
            '.ts',
            '/index.js',
            '/index.ts',
        ].map((suffix) => `${target}${suffix}`),
    ];

    for (const candidate of candidates) {
        for (const [from, to] of moves) {
            if (candidate === from || candidate.startsWith(`${from}/`)) {
                return `${to}${candidate.slice(from.length)}`;
            }
        }
    }

    return target;
}

/** The path from a file to a target, both relative to the Administration root, as an import writes it. */
const importPath = (fromFile: string, target: string) => {
    const relativePath = posix.relative(posix.dirname(fromFile), target) || '.';
    return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
};

/** Where an import path written in a file leads, relative to the Administration root. */
const resolve = (file: string, specifier: string) =>
    specifier.startsWith('src/') ? posix.normalize(specifier) : posix.join(posix.dirname(file), specifier);

/** Why a file outside the module may import the moved component, for the boundaries' disable line. */
const DISABLE_REASONS = {
    component: 'it uses the component itself, not its declaration',
    bare: 'a bare import that registers nothing, as before the module moved',
};

/**
 * Rewrites the relative and `src/…` import paths of a file whose place or targets moved, so they still reach
 * their targets: a spec in tests/ that imported './index' now imports '../src/<name>'.
 * An import through the alias stays one, and changes only when its target moved; a query such as `?raw` stays,
 * and of a template literal it rewrites the directory before the first `${`.
 * Given the moved `entry`, a file outside the module imports the module instead of the old entry:
 * a bare import of a public module stays, as its index.ts has no side effects either;
 * a bare import of a private module imports its private.ts, because its register.ts would register the component
 * a second time in a spec; and any other import takes the component from src/.
 * Both of the latter get a disabled lint line that says why.
 *
 * @example
 * // src/sw-x.js was index.js: '../../service/a.service' → '../../../service/a.service'
 * // in another module's spec: import 'src/app/component/sw-x'; → import 'src/app/component/sw-x/private';
 */
export function rewriteMovedImports(
    source: string,
    { moves, from, to, entry }: { moves: Map<string, string>; from: string; to: string; entry?: MovedEntry },
): string {
    const disabledLines = new Map<number, string>();
    const rewritten = source.replace(
        SPECIFIER,
        (statement, prefix: string, quoted: string | undefined, template: string | undefined, offset: number) => {
            const specifier = quoted ?? template ?? '';
            const isAlias = specifier.startsWith('src/');
            const rewrite = (written: string) => (quoted === undefined ? `${prefix}\`${written}` : `${prefix}'${written}'`);

            if (template !== undefined) {
                const directory = specifier.slice(0, specifier.lastIndexOf('/'));
                const target = resolve(from, directory);
                const moved = movedPath(moves, target);
                const written = isAlias ? moved : importPath(to, moved);

                return isAlias && moved === target ? statement : rewrite(`${written}${specifier.slice(directory.length)}`);
            }

            const [pathPart, query = ''] = specifier.split(/(?=\?)/);
            const target = resolve(from, pathPart);
            // An import names the module's directory or its old index file; a directory may itself be named `index`.
            const namesDirectory = entry && target === entry.directory;
            if (entry && (namesDirectory || target.replace(/\/index(\.[jt]s)?$/, '') === entry.directory)) {
                const written = namesDirectory ? pathPart : pathPart.replace(/\/index(\.[jt]s)?$/, '');
                const lineStart = source.lastIndexOf('\n', offset) + 1;
                const isBare = /^import\s*$/.test(source.slice(lineStart, offset + prefix.length));

                const literalLine = source.lastIndexOf('\n', offset + prefix.length) + 1;
                if (isBare && !entry.isPrivate) {
                    return statement;
                }

                if (isBare) {
                    disabledLines.set(literalLine, DISABLE_REASONS.bare);
                    return rewrite(`${written}/private`);
                }

                if (!prefix.startsWith('require') && !prefix.startsWith('jest')) {
                    disabledLines.set(literalLine, DISABLE_REASONS.component);
                }

                return rewrite(`${written}/src/${entry.name}`);
            }

            const moved = movedPath(moves, target);
            const written = SCRIPT_EXTENSION.test(pathPart) ? moved : moved.replace(SCRIPT_EXTENSION, '');
            if (isAlias || from === to) {
                return moved === target ? statement : rewrite(`${isAlias ? written : importPath(to, written)}${query}`);
            }

            return rewrite(`${importPath(to, written)}${query}`);
        },
    );

    return disabledLines.size === 0 ? rewritten : addDisableLines(source, rewritten, disabledLines);
}

/**
 * Puts the boundaries' disable line with its reason above each line of the original source that starts at one
 * of the offsets. The rewrite changed only import paths, so the lines are the same in both and found by their number.
 */
const addDisableLines = (original: string, rewritten: string, reasons: Map<number, string>) => {
    const byLine = new Map([...reasons].map(([start, reason]) => [original.slice(0, start).split('\n').length - 1, reason]));
    const lines = rewritten.split('\n');

    return lines
        .flatMap((line, number) => {
            const reason = byLine.get(number);
            if (!reason || lines[number - 1]?.includes('sw-core-rules/mini-module-boundaries')) {
                return [line];
            }

            const indent = /^\s*/.exec(line)?.[0] ?? '';
            return [`${indent}// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- ${reason}`, line];
        })
        .join('\n');
};

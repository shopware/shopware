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

const DISABLE_BOUNDARY =
    '// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- it uses the component itself, not its declaration';

/**
 * Rewrites the relative and `src/…` import paths of a file whose place or targets moved, so they still reach
 * their targets: a spec in tests/ that imported './index' now imports '../src/<name>'.
 * An import through the alias stays one, and changes only when its target moved; a query such as `?raw` stays,
 * and of a template literal it rewrites the directory before the first `${`.
 * Given the moved `entry`, a file outside the module imports the module instead of the old entry:
 * a bare import of a private module imports its register.ts, a bare import of a public one stays,
 * and any other import takes the component from src/, with a disabled lint line that says why.
 *
 * @example
 * // src/sw-x.js was index.js: '../../service/a.service' → '../../../service/a.service'
 * // in another module's spec: import 'src/app/component/sw-x'; → import 'src/app/component/sw-x/register';
 */
export function rewriteMovedImports(
    source: string,
    { moves, from, to, entry }: { moves: Map<string, string>; from: string; to: string; entry?: MovedEntry },
): string {
    const disabledLines = new Set<number>();
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
            const entryDirectory = entry && target.replace(/\/index(\.[jt]s)?$/, '');
            if (entry && entryDirectory === entry.directory) {
                const written = pathPart.replace(/\/index(\.[jt]s)?$/, '');
                const lineStart = source.lastIndexOf('\n', offset) + 1;
                const isBare = /^import\s*$/.test(source.slice(lineStart, offset + prefix.length));

                if (isBare) {
                    return entry.isPrivate ? rewrite(`${written}/register`) : statement;
                }

                if (!prefix.startsWith('require') && !prefix.startsWith('jest')) {
                    disabledLines.add(source.lastIndexOf('\n', offset + prefix.length) + 1);
                }

                return rewrite(`${written}/src/${entry.name}`);
            }

            const moved = movedPath(moves, target);
            const written = SCRIPT_EXTENSION.test(pathPart) ? moved : moved.replace(SCRIPT_EXTENSION, '');
            if (isAlias) {
                return moved === target ? statement : rewrite(`${written}${query}`);
            }

            return rewrite(`${importPath(to, written)}${query}`);
        },
    );

    return disabledLines.size === 0 ? rewritten : addDisableLines(source, rewritten, disabledLines);
}

/**
 * Puts the boundaries' disable line above each line of the original source that starts at one of the offsets.
 * The rewrite changed only import paths, so the lines are the same in both and are found by their number.
 */
const addDisableLines = (original: string, rewritten: string, lineStarts: Set<number>) => {
    const lineNumbers = new Set([...lineStarts].map((start) => original.slice(0, start).split('\n').length - 1));
    const lines = rewritten.split('\n');

    return lines
        .flatMap((line, number) => {
            if (!lineNumbers.has(number) || lines[number - 1]?.includes('sw-core-rules/mini-module-boundaries')) {
                return [line];
            }

            return [`${/^\s*/.exec(line)?.[0] ?? ''}${DISABLE_BOUNDARY}`, line];
        })
        .join('\n');
};

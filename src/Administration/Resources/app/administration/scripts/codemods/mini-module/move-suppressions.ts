/**
 * @sw-package framework
 * @private
 */

/**
 * The ESLint suppressions after a move, sorted by file: each key follows its file, also a file inside a moved
 * directory such as a split spec `sw-x.spec/`. A key below the component's old directory whose file `exists`
 * no longer finds after the move is dropped, because nothing can report it any more.
 *
 * @example
 * moveSuppressionKeys(
 *     { 'a/x.spec/b.spec.js': { 'no-vm-access': { count: 2 } } },
 *     new Map([['a/x.spec', 'a/tests/x.spec']]),
 *     'a',
 *     () => true,
 * );
 * // → { 'a/tests/x.spec/b.spec.js': { 'no-vm-access': { count: 2 } } }
 */
export function moveSuppressionKeys<T>(
    suppressions: Record<string, T>,
    moves: Map<string, string>,
    origin: string,
    exists: (file: string) => boolean,
): Record<string, T> {
    const follow = (key: string) => {
        for (const [from, to] of moves) {
            if (key === from || key.startsWith(`${from}/`)) {
                return `${to}${key.slice(from.length)}`;
            }
        }

        return key;
    };

    const moved = Object.entries(suppressions)
        .map(([key, value]) => [follow(key), value] as const)
        .filter(([key]) => !key.startsWith(`${origin}/`) || exists(key));

    return Object.fromEntries(moved.sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0)));
}

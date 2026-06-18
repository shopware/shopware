/**
 * @sw-package framework
 */

const { decode, encode } = require('@jridgewell/sourcemap-codec');

/**
 * Tracks a generated output cursor after appending code.
 *
 * @param {{ line: number, column: number }} position
 * @param {string} code
 * @returns {void}
 */
function moveGeneratedCursor(position, code) {
    const lines = code.split('\n');

    if (lines.length === 1) {
        position.column += code.length;

        return;
    }

    position.line += lines.length - 1;
    position.column = lines[lines.length - 1].length;
}

/**
 * Returns generated-only mapping positions at the start and end of generated snippets.
 *
 * Sourcemap consumers can ask for the closest mapping before or after a generated column.
 * Adding both edges prevents generated bridge code from being attributed to nearby user code.
 *
 * @param {{ line: number, column: number }} position
 * @param {string} code
 * @returns {{ line: number, column: number }[]}
 */
function findGeneratedOnlyMappingEdges(position, code) {
    const positions = [{ ...position }];
    let line = position.line;
    let column = position.column;

    code.split('\n').forEach((part, index, parts) => {
        if (part.length > 0) {
            positions.push({
                line,
                column: column + part.length - 1,
            });
        }

        if (index < parts.length - 1) {
            line += 1;
            column = 0;
        }
    });

    return positions;
}

/**
 * Inserts generated-only mapping segments into a sourcemap.
 *
 * MagicString intentionally leaves generated-only chunks without original locations. This
 * second pass also blocks closest-match lookups from drifting into neighboring user code.
 *
 * @param {import('magic-string').SourceMap} map
 * @param {{ line: number, column: number }[]} positions
 * @returns {void}
 */
function markGeneratedCodeAsUnmapped(map, positions) {
    if (positions.length === 0) {
        return;
    }

    const decodedMappings = decode(map.mappings);

    positions.forEach((position) => {
        const line = position.line - 1;

        if (!decodedMappings[line]) {
            decodedMappings[line] = [];
        }

        const existingIndex = decodedMappings[line].findIndex((segment) => segment[0] === position.column);

        if (existingIndex === -1) {
            decodedMappings[line].push([position.column]);
        } else {
            decodedMappings[line][existingIndex] = [position.column];
        }

        decodedMappings[line].sort((a, b) => a[0] - b[0]);
    });

    map.mappings = encode(decodedMappings);
}

module.exports = {
    findGeneratedOnlyMappingEdges,
    markGeneratedCodeAsUnmapped,
    moveGeneratedCursor,
};

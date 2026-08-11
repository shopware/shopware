/**
 * @sw-package framework
 */

import { decode, encode } from '@jridgewell/sourcemap-codec';

/**
 * A cursor into the generated (rewritten) output.
 *
 * `line` is 1-based and `column` is 0-based, matching MagicString's own cursor and the
 * sourcemap column convention. The 1-based line is why {@link markGeneratedCodeAsUnmapped}
 * subtracts one before indexing into the decoded (0-based) mappings array.
 */
type GeneratedPosition = {
    line: number;
    column: number;
};

/**
 * The minimal structural view of a source map this module needs: just its VLQ `mappings`
 * string. Kept narrow so the mappings can be decoded, mutated, and re-encoded in place
 * without depending on the full {@link SourceMap} shape.
 */
type MutableMappingsSourceMap = {
    mappings: string;
};

/**
 * Advances a {@link GeneratedPosition} as if `code` were appended at the cursor.
 *
 * Single-line code only moves the column; a newline resets the column to the length of the
 * final line. Callers keep this cursor in step with the bundle so generated-only positions
 * can be recorded at the correct generated coordinates.
 */
function moveGeneratedCursor(position: GeneratedPosition, code: string): void {
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
 */
function findGeneratedOnlyMappingEdges(position: GeneratedPosition, code: string): GeneratedPosition[] {
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
 * Concept: a decoded mapping segment is `[genColumn, sourceIndex, sourceLine, sourceColumn]`.
 * A single-element `[genColumn]` segment is a valid but *unmapped* marker — it names a
 * generated column that points at no original source. Writing one at each edge column pins the
 * boundary so a consumer resolving a nearby column cannot bleed into an author-owned segment.
 */
function markGeneratedCodeAsUnmapped(map: MutableMappingsSourceMap, positions: GeneratedPosition[]): void {
    if (positions.length === 0) {
        return;
    }

    const decodedMappings = decode(map.mappings);

    positions.forEach((position) => {
        const line = position.line - 1;

        if (!decodedMappings[line]) {
            decodedMappings[line] = [];
        }

        const existingIndex = decodedMappings[line].findIndex((segment: number[]) => segment[0] === position.column);

        if (existingIndex === -1) {
            decodedMappings[line].push([position.column]);
        } else {
            decodedMappings[line][existingIndex] = [position.column];
        }

        decodedMappings[line].sort((a: number[], b: number[]) => a[0] - b[0]);
    });

    map.mappings = encode(decodedMappings);
}

export {
    type GeneratedPosition,
    type MutableMappingsSourceMap,
    findGeneratedOnlyMappingEdges,
    markGeneratedCodeAsUnmapped,
    moveGeneratedCursor,
};

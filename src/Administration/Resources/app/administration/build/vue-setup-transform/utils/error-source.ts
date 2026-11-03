/**
 * @sw-package framework
 */

const LINE_BREAK = /\r\n|[\n\r\u2028\u2029]/;

/** Unmarked source lines printed before and after the underlined range. */
const CONTEXT_LINES = 2;

/** Lines between a range's first and last line that are still printed before the middle collapses. */
const MAX_INNER_LINES = 2;

function positionAt(source: string, index: number): { line: number; column: number } {
    const lines = source.slice(0, index).split(LINE_BREAK);

    return { line: lines.length, column: lines[lines.length - 1].length };
}

/**
 * Resolves an absolute range and renders only its characters, preserving tabs in the caret padding.
 *
 * A multiline range is underlined at its first and last line only, and a long middle collapses into an
 * ellipsis like `tsc` does: a rejected 40-line declaration would otherwise print a caret line per source
 * line, doubling the output without saying anything the two ends do not already say.
 */
function resolveErrorSource(source: string, file: string, index: number, endIndex: number | null) {
    const start = Math.min(Math.max(index, 0), source.length);
    const end = Math.min(Math.max(endIndex ?? start + 1, start), source.length);
    const startPosition = positionAt(source, start);
    // The end is exclusive: a range ending at a newline must not underline the following line.
    const endPosition = positionAt(source, Math.max(start, end - 1));
    const lines = source.split(LINE_BREAK);
    const firstLine = Math.max(1, startPosition.line - CONTEXT_LINES);
    const lastLine = Math.min(lines.length, endPosition.line + CONTEXT_LINES);
    const width = Math.max(3, String(lastLine).length);
    const collapsesMiddle = endPosition.line - startPosition.line - 1 > MAX_INNER_LINES;
    const frame: string[] = [];

    for (let line = firstLine; line <= lastLine; line += 1) {
        const isInsideRange = line > startPosition.line && line < endPosition.line;

        if (collapsesMiddle && isInsideRange) {
            if (line === startPosition.line + 1) {
                frame.push('...');
            }

            continue;
        }

        const text = lines[line - 1];
        frame.push(`${String(line).padEnd(width)}|  ${text}`);

        if (isInsideRange || line < startPosition.line || line > endPosition.line) {
            continue;
        }

        const from = line === startPosition.line ? startPosition.column : 0;
        const to = line === endPosition.line ? Math.min(endPosition.column + 1, text.length) : text.length;
        const padding = text.slice(0, from).replace(/[^\t]/g, ' ');
        frame.push(`${' '.repeat(width)}|  ${padding}${'^'.repeat(Math.max(1, to - from))}`);
    }

    return { loc: { file, ...startPosition }, frame: frame.join('\n') };
}

/** @private */
export { resolveErrorSource };

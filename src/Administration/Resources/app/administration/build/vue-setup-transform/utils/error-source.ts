/**
 * @sw-package framework
 */

const LINE_BREAK = /\r\n|[\n\r\u2028\u2029]/;

function positionAt(source: string, index: number): { line: number; column: number } {
    const lines = source.slice(0, index).split(LINE_BREAK);

    return { line: lines.length, column: lines[lines.length - 1].length };
}

/** Resolves an absolute range and renders only its characters, preserving tabs in the caret padding. */
function resolveErrorSource(source: string, file: string, index: number, endIndex: number | null) {
    const start = Math.min(Math.max(index, 0), source.length);
    const end = Math.min(Math.max(endIndex ?? start + 1, start), source.length);
    const startPosition = positionAt(source, start);
    // The end is exclusive: a range ending at a newline must not underline the following line.
    const endPosition = positionAt(source, Math.max(start, end - 1));
    const lines = source.split(LINE_BREAK);
    const firstLine = Math.max(1, startPosition.line - 2);
    const lastLine = Math.min(lines.length, endPosition.line + 2);
    const width = Math.max(3, String(lastLine).length);
    const frame: string[] = [];

    for (let line = firstLine; line <= lastLine; line += 1) {
        const text = lines[line - 1];
        frame.push(`${String(line).padEnd(width)}|  ${text}`);

        if (line < startPosition.line || line > endPosition.line) {
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

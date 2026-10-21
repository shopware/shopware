/**
 * @sw-package framework
 */

export type DiffLineType = 'same' | 'added' | 'removed';

export interface DiffLine {
    type: DiffLineType;
    text: string;
}

function splitLines(text: string | null): string[] {
    return text === null || text === '' ? [] : text.replace(/\r\n/g, '\n').split('\n');
}

/**
 * Line diff over the longest common subsequence; removals come before additions in a changed hunk.
 * A matched non-blank line outweighs any number of matched blank lines, otherwise the diff anchors on
 * blank lines and reports moved-looking removals and additions of unchanged code.
 */
export function diffLines(before: string | null, after: string | null): DiffLine[] {
    const a = splitLines(before);
    const b = splitLines(after);
    const heavy = Math.min(a.length, b.length) + 1;
    const weight = (line: string): number => (line.trim() === '' ? 1 : heavy);
    const scores: number[][] = Array.from({ length: a.length + 1 }, () => new Array<number>(b.length + 1).fill(0));

    for (let i = a.length - 1; i >= 0; i -= 1) {
        for (let j = b.length - 1; j >= 0; j -= 1) {
            scores[i][j] =
                a[i] === b[j] ? scores[i + 1][j + 1] + weight(a[i]) : Math.max(scores[i + 1][j], scores[i][j + 1]);
        }
    }

    const lines: DiffLine[] = [];
    let i = 0;
    let j = 0;

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            lines.push({ type: 'same', text: a[i] });
            i += 1;
            j += 1;
        } else if (scores[i + 1][j] >= scores[i][j + 1]) {
            lines.push({ type: 'removed', text: a[i] });
            i += 1;
        } else {
            lines.push({ type: 'added', text: b[j] });
            j += 1;
        }
    }

    a.slice(i).forEach((text) => lines.push({ type: 'removed', text }));
    b.slice(j).forEach((text) => lines.push({ type: 'added', text }));

    return lines;
}

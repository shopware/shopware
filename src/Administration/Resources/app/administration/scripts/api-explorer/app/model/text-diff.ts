/**
 * @sw-package framework
 */

/** Pairs where both sides are shorter than this are shown in full. */
export const LONG_TEXT_LENGTH = 120;
const CONTEXT = 40;
/** Token matrix cells; beyond this the middle is shown as one removed and one added run. */
const MAX_CELLS = 4_000_000;

export type TextSegmentType = 'same' | 'removed' | 'added' | 'gap';

export interface TextSegment {
    type: TextSegmentType;
    text: string;
}

export function isLongPair(before: string | null, after: string | null): boolean {
    return (
        before !== null &&
        after !== null &&
        before !== after &&
        (before.length >= LONG_TEXT_LENGTH || after.length >= LONG_TEXT_LENGTH)
    );
}

function tokenize(text: string): string[] {
    return text.split(/(\s+|[;,:?|&(){}[\]<>=])/).filter((token) => token !== '');
}

function diffTokens(a: string[], b: string[]): TextSegment[] {
    let start = 0;
    while (start < a.length && start < b.length && a[start] === b[start]) {
        start += 1;
    }

    let end = 0;
    while (end < a.length - start && end < b.length - start && a[a.length - 1 - end] === b[b.length - 1 - end]) {
        end += 1;
    }

    const midA = a.slice(start, a.length - end);
    const midB = b.slice(start, b.length - end);
    const segments: TextSegment[] = [{ type: 'same', text: a.slice(0, start).join('') }];

    if ((midA.length + 1) * (midB.length + 1) > MAX_CELLS) {
        segments.push({ type: 'removed', text: midA.join('') }, { type: 'added', text: midB.join('') });
    } else {
        const lengths: number[][] = Array.from({ length: midA.length + 1 }, () =>
            new Array<number>(midB.length + 1).fill(0),
        );

        for (let i = midA.length - 1; i >= 0; i -= 1) {
            for (let j = midB.length - 1; j >= 0; j -= 1) {
                lengths[i][j] =
                    midA[i] === midB[j] ? lengths[i + 1][j + 1] + 1 : Math.max(lengths[i + 1][j], lengths[i][j + 1]);
            }
        }

        let i = 0;
        let j = 0;
        while (i < midA.length || j < midB.length) {
            if (i < midA.length && j < midB.length && midA[i] === midB[j]) {
                segments.push({ type: 'same', text: midA[i] });
                i += 1;
                j += 1;
            } else if (j >= midB.length || (i < midA.length && lengths[i + 1][j] >= lengths[i][j + 1])) {
                segments.push({ type: 'removed', text: midA[i] });
                i += 1;
            } else {
                segments.push({ type: 'added', text: midB[j] });
                j += 1;
            }
        }
    }

    segments.push({ type: 'same', text: a.slice(a.length - end).join('') });

    return segments.reduce<TextSegment[]>((merged, segment) => {
        const last = merged[merged.length - 1];

        if (segment.text === '') {
            return merged;
        }

        if (last && last.type === segment.type) {
            last.text += segment.text;
        } else {
            merged.push({ ...segment });
        }

        return merged;
    }, []);
}

/**
 * The changed parts of a long pair with about `context` characters of unchanged text around each of
 * them; longer unchanged runs collapse into a `gap`.
 */
export function compactTextDiff(before: string, after: string, context = CONTEXT): TextSegment[] {
    const segments = diffTokens(tokenize(before), tokenize(after));
    const gap: TextSegment = { type: 'gap', text: '…' };

    return segments.flatMap((segment, index): TextSegment[] => {
        if (segment.type !== 'same') {
            return [segment];
        }

        const first = index === 0;
        const last = index === segments.length - 1;
        const { text } = segment;

        if (first && last) {
            return [segment];
        }

        if (first) {
            return text.length > context ? [gap, { type: 'same', text: text.slice(-context) }] : [segment];
        }

        if (last) {
            return text.length > context ? [{ type: 'same', text: text.slice(0, context) }, gap] : [segment];
        }

        return text.length > 2 * context + 1
            ? [
                  { type: 'same', text: text.slice(0, context) },
                  gap,
                  { type: 'same', text: text.slice(-context) },
              ]
            : [segment];
    });
}

/**
 * @sw-package framework
 *
 * The code a view shows for one finding, cut out of the sources of the base and the head commit: the lines that
 * changed, and where the comment of the fix goes.
 */
import { sourceLineOf } from '../break-check/finding';
import type { ExplainedFinding } from '../break-check/finding';
import type { SourceLine } from '../model/source-line';
import type { SourceTree } from '../source/source-tree';

/**
 * @private
 *
 * One line of a block of code. `-` is only in the base commit and `+` only in the head commit. `…` stands for
 * lines between two changes that the block leaves out. `before` and `after` are the numbers of the line in the
 * two commits.
 */
export interface CodeLine {
    type: ' ' | '-' | '+' | '…';
    before?: number;
    after?: number;
    text: string;
}

/** @private */
export interface CodeBlock {
    file: string;
    lines: CodeLine[];
}

/** Lines around a change that did not change. */
const CONTEXT = 2;
/** A change with more lines is cut down to the lines around the one the finding is about. */
const LONGEST_CHANGE = 16;
/** The lines above the line of an element that belong to it, as its comment does. */
const ABOVE = 3;
/** The lines below the line of an element that belong to its declaration, as the options of a prop do. */
const BELOW = 6;
/** A finding whose own lines did not change shows at most this many changes that name its element. */
const MOST_CHANGES = 2;
/** A change that names the element belongs to it when it is no further from its line than this. */
const NEARBY = 150;
/** Two files with more lines between their common start and end are not compared line by line. */
const LARGEST_TABLE = 6_000_000;

const LEFT_OUT: CodeLine = { type: '…', text: '' };

type Step = ' ' | '-' | '+';

function linesOf(tree: SourceTree, file: string): string[] {
    return tree.has(file) ? tree.read(file).split(/\r?\n/) : [];
}

/**
 * The steps from `base` to `head` that keep the longest run of lines both share. Two texts that are too long
 * for the table of that run are taken apart as a whole.
 */
function stepsBetween(base: readonly string[], head: readonly string[]): Step[] {
    if (base.length * head.length > LARGEST_TABLE) {
        return [...base.map((): Step => '-'), ...head.map((): Step => '+')];
    }

    // shared[row * width + column] is the longest run that the lines from `row` and from `column` on share.
    const width = head.length + 1;
    const shared = new Uint32Array((base.length + 1) * width);

    for (let row = base.length - 1; row >= 0; row -= 1) {
        for (let column = head.length - 1; column >= 0; column -= 1) {
            shared[row * width + column] =
                base[row] === head[column]
                    ? shared[(row + 1) * width + column + 1] + 1
                    : Math.max(shared[(row + 1) * width + column], shared[row * width + column + 1]);
        }
    }

    const steps: Step[] = [];
    let row = 0;
    let column = 0;

    while (row < base.length || column < head.length) {
        if (row < base.length && column < head.length && base[row] === head[column]) {
            steps.push(' ');
            row += 1;
            column += 1;
        } else if (
            row < base.length &&
            (column === head.length || shared[(row + 1) * width + column] >= shared[row * width + column + 1])
        ) {
            steps.push('-');
            row += 1;
        } else {
            steps.push('+');
            column += 1;
        }
    }

    return steps;
}

/** The lines of both texts in the order of a diff, each with its number in the text it is from. */
function compared(base: readonly string[], head: readonly string[]): CodeLine[] {
    let start = 0;

    while (start < base.length && start < head.length && base[start] === head[start]) {
        start += 1;
    }

    let end = 0;

    while (end < base.length - start && end < head.length - start && base.at(-1 - end) === head.at(-1 - end)) {
        end += 1;
    }

    const kept = (count: number): Step[] => Array.from({ length: count }, (): Step => ' ');
    const steps = [
        ...kept(start),
        ...stepsBetween(base.slice(start, base.length - end), head.slice(start, head.length - end)),
        ...kept(end),
    ];
    let before = 0;
    let after = 0;

    return steps.map((type): CodeLine => {
        before += type === '+' ? 0 : 1;
        after += type === '-' ? 0 : 1;

        if (type === '+') {
            return { type, after, text: head[after - 1] };
        }

        return type === '-' ? { type, before, text: base[before - 1] } : { type, before, after, text: base[before - 1] };
    });
}

/** A run of changed lines by the indexes of its first and last line. */
interface Run {
    first: number;
    last: number;
}

/** A run with the index of the line in it that the finding is about. */
interface Change extends Run {
    pivot: number;
}

function runsOf(lines: readonly CodeLine[]): Run[] {
    const runs: Run[] = [];

    lines.forEach((line, index) => {
        const open = index > 0 && lines[index - 1].type !== ' ' ? runs.at(-1) : undefined;

        if (line.type === ' ') {
            return;
        }

        if (open === undefined) {
            runs.push({ first: index, last: index });
        } else {
            open.last = index;
        }
    });

    return runs;
}

function mentions(name: string): (line: CodeLine) => boolean {
    const pattern = new RegExp(`(?<![\\w$])${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?![\\w$])`);

    return ({ text }) => pattern.test(text);
}

/** The name of the element itself, the last part of its id: `itemIndex` of `block:sw_card/scope:itemIndex`. */
function ownName({ element }: ExplainedFinding): string {
    const part = element.slice(element.lastIndexOf('/') + 1);

    return part.slice(part.indexOf(':') + 1);
}

/**
 * The changes that the finding is about, `own` being the indexes of the lines of its element. They are the
 * changes in those lines and in the few lines around them that belong to the element. When these read the same
 * in both commits, they are the closest changes that name the element, as a template variable goes away in the
 * line of its slot and not in the line of the block. Any other change of the file says nothing about the finding.
 */
function changesAbout(finding: ExplainedFinding, lines: readonly CodeLine[], own: readonly number[]): Change[] {
    const runs = runsOf(lines);
    const ofElement = runs.flatMap((run) => {
        const line = own.find((index) => run.first <= index + BELOW && run.last >= index - ABOVE);

        return line === undefined ? [] : [{ ...run, pivot: Math.min(Math.max(line, run.first), run.last) }];
    });

    if (ofElement.length > 0 || own.length === 0) {
        return ofElement;
    }

    const names = mentions(ownName(finding));
    const distance = ({ first, last }: Run): number => Math.max(first - own[0], own[0] - last);

    return runs
        .flatMap((run) => {
            const offset = lines.slice(run.first, run.last + 1).findIndex(names);

            return offset === -1 || distance(run) > NEARBY ? [] : [{ ...run, pivot: run.first + offset }];
        })
        .sort((one, other) => distance(one) - distance(other))
        .slice(0, MOST_CHANGES);
}

/** Lines of the file that the block shows, with whether the lines right before or after them are left out. */
interface Shown {
    from: number;
    to: number;
    isCutBefore: boolean;
    isCutAfter: boolean;
}

/**
 * The change with the lines around it. A long change is cut down to the lines around the one the finding is
 * about, and an end that is cut has no lines around it.
 */
function shown({ first, last, pivot }: Change, lines: readonly CodeLine[]): Shown {
    const isLong = last - first >= LONGEST_CHANGE;
    const from = isLong ? Math.max(first, pivot - LONGEST_CHANGE / 2) : first;
    const to = isLong ? Math.min(last, pivot + LONGEST_CHANGE / 2) : last;

    return {
        from: from === first ? Math.max(0, from - CONTEXT) : from,
        to: to === last ? Math.min(lines.length - 1, to + CONTEXT) : to,
        isCutBefore: from !== first,
        isCutAfter: to !== last,
    };
}

function isBlank({ type, text }: CodeLine): boolean {
    return type === ' ' && text.trim() === '';
}

/**
 * One block of the shown lines in the order of the file, with a `…` wherever lines are left out between them or
 * inside a change that is cut. An empty line at either end of the block says nothing, so it goes.
 */
function blockOf(shownLines: readonly Shown[], lines: readonly CodeLine[]): CodeLine[] {
    const block: CodeLine[] = [];
    const leaveOut = (): void => {
        if (block.at(-1) !== LEFT_OUT) {
            block.push(LEFT_OUT);
        }
    };
    let reached = -1;

    [...shownLines]
        .sort((one, other) => one.from - other.from)
        .forEach(({ from, to, isCutBefore, isCutAfter }) => {
            if (to <= reached) {
                return;
            }

            if (isCutBefore || (reached !== -1 && from > reached + 1)) {
                leaveOut();
            }

            block.push(...lines.slice(Math.max(from, reached + 1), to + 1));
            reached = to;

            if (isCutAfter) {
                leaveOut();
            }
        });

    const first = block.findIndex((line) => !isBlank(line));

    return block.slice(first, block.findLastIndex((line) => !isBlank(line)) + 1);
}

/**
 * @private
 *
 * The lines that changed for the finding in the file of its element, with a few lines around them and with the
 * line of the element itself, so the reader sees where it is. `undefined` when no change of that file is about
 * the finding.
 */
export function changedCode(finding: ExplainedFinding, base: SourceTree, head: SourceTree): CodeBlock | undefined {
    const { atBase, isGone } = finding.detail;
    const at = finding.at === undefined || isGone ? undefined : sourceLineOf(finding.at);
    const file = atBase?.file ?? at?.file;

    if (file === undefined) {
        return undefined;
    }

    const lines = compared(linesOf(base, file), linesOf(head, file));
    const own = lines.flatMap(({ before, after }, index) =>
        (before !== undefined && before === atBase?.line) || (after !== undefined && at?.file === file && after === at.line)
            ? [index]
            : [],
    );
    const changes = changesAbout(finding, lines, own);

    if (changes.length === 0) {
        return undefined;
    }

    return {
        file,
        lines: blockOf(
            [
                ...changes.map((change) => shown(change, lines)),
                ...own.map((index) => ({ from: index, to: index, isCutBefore: false, isCutAfter: false })),
            ],
            lines,
        ),
    };
}

function lineAt(tree: SourceTree, place: SourceLine | undefined): (SourceLine & { text: string }) | undefined {
    const text = place && linesOf(tree, place.file).at(place.line - 1);

    return place === undefined || text === undefined ? undefined : { ...place, text };
}

/**
 * @private
 *
 * Where the comment of the fix goes: above the line of the element, with its indentation. An element that is
 * gone has the line it had at the base commit, because the fix keeps it.
 */
export function fixCode(finding: ExplainedFinding, base: SourceTree, head: SourceTree): CodeBlock | undefined {
    const { atBase, isGone, marker } = finding.detail;
    const line = isGone || finding.at === undefined ? lineAt(base, atBase) : lineAt(head, sourceLineOf(finding.at));

    if (marker === undefined || line === undefined) {
        return undefined;
    }

    return {
        file: line.file,
        lines: [
            { type: '+', text: `${/^\s*/.exec(line.text)?.[0] ?? ''}${marker}` },
            { type: ' ', text: line.text },
        ],
    };
}

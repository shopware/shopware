/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import { parseDeprecation } from './doc-comments';
import { nestedBlockPlaceholder, type BlockEntry, type NamedEntry, type SnapshotDiagnostic } from '../types';

export interface TemplateSource {
    /** Admin-root relative */
    file: string;
    text: string;
    /** Lines before the template text, for templates inlined in a script. */
    lineOffset: number;
}

export interface ParsedTemplate {
    blocks: BlockEntry[];
    slots: NamedEntry[];
    emits: NamedEntry[];
    diagnostics: SnapshotDiagnostic[];
}

interface OpenBlock {
    name: string;
    start: number;
    innerStart: number;
    children: OpenBlock[];
    innerEnd: number;
    end: number;
    entry: BlockEntry;
}

interface Comment {
    start: number;
    end: number;
    text: string;
}

const TOKEN = /\{#[\s\S]*?#\}|\{%-?\s*(\w+)\b\s*([\w.-]*)[\s\S]*?-?%\}/g;
const END_RAW = /\{%-?\s*end(?:raw|verbatim)\b[\s\S]*?-?%\}/g;
const SLOT = /<slot\b((?:\s+[^\s=>/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/?>/g;
const EMIT = /\$emit\(\s*(['"])([^'"]+)\1/g;

export function hashBlockContent(content: string): string {
    return createHash('sha1').update(content.replace(/\s+/g, ' ').trim()).digest('hex');
}

function lineLocator(text: string, lineOffset: number): (offset: number) => number {
    const starts = [0];

    for (let index = text.indexOf('\n'); index !== -1; index = text.indexOf('\n', index + 1)) {
        starts.push(index + 1);
    }

    return (offset) => {
        let low = 0;
        let high = starts.length - 1;

        while (low < high) {
            const middle = (low + high + 1) >> 1;

            if (starts[middle] <= offset) {
                low = middle;
            } else {
                high = middle - 1;
            }
        }

        return low + 1 + lineOffset;
    };
}

/** A first line that shares its line with the opening tag carries no indentation of its own. */
export function dedent(text: string): string {
    const startsOnTagLine = !/^[ \t]*\r?\n/.test(text);
    const lines = text
        .replace(/^(?:[ \t]*\r?\n)+/, '')
        .trimEnd()
        .split(/\r?\n/);
    const indentOf = (line: string): number => line.length - line.trimStart().length;
    const indents = lines.filter((line, index) => line.trim() !== '' && !(index === 0 && startsOnTagLine)).map(indentOf);
    const indent = indents.length > 0 ? Math.min(...indents) : 0;

    return lines
        .map((line, index) =>
            index === 0 && startsOnTagLine ? line.trimStart() : line.slice(Math.min(indent, indentOf(line))),
        )
        .join('\n');
}

function maskComments(text: string): string {
    return text.replace(/\{#[\s\S]*?#\}|<!--[\s\S]*?-->/g, (comment) => comment.replace(/[^\n]/g, ' '));
}

function deprecationAround(
    block: OpenBlock,
    text: string,
    commentsByEnd: Map<number, Comment>,
    commentsByStart: Map<number, Comment>,
) {
    let before = block.start;

    while (before > 0 && /\s/.test(text[before - 1])) {
        before -= 1;
    }

    let inside = block.innerStart;

    while (inside < text.length && /\s/.test(text[inside])) {
        inside += 1;
    }

    const candidates = [
        commentsByEnd.get(before),
        commentsByStart.get(inside),
    ];

    return candidates.map((comment) => (comment ? parseDeprecation(comment.text) : null)).find((entry) => entry) ?? null;
}

export function parseTemplate({ file, text, lineOffset }: TemplateSource): ParsedTemplate {
    const lineAt = lineLocator(text, lineOffset);
    const diagnostics: SnapshotDiagnostic[] = [];
    const root: OpenBlock[] = [];
    const stack: OpenBlock[] = [];
    const all: OpenBlock[] = [];
    const commentsByEnd = new Map<number, Comment>();
    const commentsByStart = new Map<number, Comment>();
    const pathCounts = new Map<string, number>();
    const pathOf = new Map<OpenBlock, string>();

    TOKEN.lastIndex = 0;

    for (let match = TOKEN.exec(text); match; match = TOKEN.exec(text)) {
        const [tag, keyword, argument] = match;
        const start = match.index;

        if (tag.startsWith('{#')) {
            const comment = { start, end: start + tag.length, text: tag };
            commentsByEnd.set(comment.end, comment);
            commentsByStart.set(comment.start, comment);
            continue;
        }

        if (keyword === 'raw' || keyword === 'verbatim') {
            END_RAW.lastIndex = TOKEN.lastIndex;
            const end = END_RAW.exec(text);
            TOKEN.lastIndex = end ? end.index + end[0].length : text.length;
            continue;
        }

        if (keyword === 'block') {
            const parent = stack[stack.length - 1];
            const namePath = parent ? `${pathOf.get(parent)}/${argument}` : argument;
            const occurrence = (pathCounts.get(namePath) ?? 0) + 1;
            pathCounts.set(namePath, occurrence);

            const block: OpenBlock = {
                name: argument,
                start,
                innerStart: start + tag.length,
                children: [],
                innerEnd: text.length,
                end: text.length,
                entry: {
                    name: argument,
                    key: occurrence > 1 ? `${namePath}~${occurrence}` : namePath,
                    kind: 'twig',
                    parent: parent?.name ?? null,
                    depth: stack.length,
                    content: '',
                    contentHash: '',
                    visibility: 'public',
                    deprecation: null,
                    location: { file, line: lineAt(start) },
                },
            };

            pathOf.set(block, namePath);
            (parent ? parent.children : root).push(block);
            all.push(block);
            stack.push(block);
            continue;
        }

        if (keyword === 'endblock') {
            const block = stack.pop();

            if (!block) {
                diagnostics.push({ file, message: `Unexpected endblock in line ${lineAt(start)}.` });
                continue;
            }

            if (argument && argument !== block.name) {
                diagnostics.push({
                    file,
                    message: `endblock ${argument} in line ${lineAt(start)} closes block ${block.name}.`,
                });
            }

            block.innerEnd = start;
            block.end = start + tag.length;
        }
    }

    for (const block of stack) {
        diagnostics.push({ file, message: `Block ${block.name} in line ${block.entry.location?.line} is not closed.` });
    }

    for (const block of all) {
        let content = '';
        let position = block.innerStart;

        for (const child of block.children) {
            content += text.slice(position, child.start) + nestedBlockPlaceholder(child.name);
            position = child.end;
        }

        content += text.slice(position, block.innerEnd);
        block.entry.content = dedent(content);
        block.entry.contentHash = hashBlockContent(block.entry.content);
        block.entry.deprecation = deprecationAround(block, text, commentsByEnd, commentsByStart);
    }

    return { blocks: all.map(({ entry }) => entry), ...parseSlotsAndEmits(file, text, lineAt), diagnostics };
}

function parseSlotsAndEmits(
    file: string,
    text: string,
    lineAt: (offset: number) => number,
): { slots: NamedEntry[]; emits: NamedEntry[] } {
    const masked = maskComments(text);
    const slots = new Map<string, NamedEntry>();
    const emits = new Map<string, NamedEntry>();
    const meta = (offset: number) => ({
        visibility: 'public' as const,
        deprecation: null,
        location: { file, line: lineAt(offset) },
    });

    for (const match of masked.matchAll(SLOT)) {
        const attributes = match[1];

        if (/(?:^|\s)(?::|v-bind:)name\s*=/.test(attributes)) {
            continue;
        }

        const named = /(?:^|\s)name\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/.exec(attributes);
        const name = named ? (named[1] ?? named[2] ?? named[3]) : 'default';

        if (!/[{}]/.test(name) && !slots.has(name)) {
            slots.set(name, { name, ...meta(match.index) });
        }
    }

    for (const match of masked.matchAll(EMIT)) {
        if (!emits.has(match[2])) {
            emits.set(match[2], { name: match[2], ...meta(match.index) });
        }
    }

    return { slots: [...slots.values()], emits: [...emits.values()] };
}

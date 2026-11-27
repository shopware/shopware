/**
 * @sw-package framework
 *
 * The template a component renders as one piece: the merged block tree as HTML again, parsed once. Its readers
 * see the markup of an extend child between the markup of its parent, the way Vue compiles it.
 */
import type { TemplateChildNode } from '@vue/compiler-dom';
import type { SourceLine } from '../../model/source-line';
import { isBlock, isMarkup } from './block-tree';
import type { BlockContent, BlockNode, Markup } from './block-tree';
import { parseTemplate } from './template-nodes';

/**
 * @private
 *
 * `nodes` is the parsed `html`, which holds a comment where a block starts and ends.
 */
export interface RenderedTemplate {
    html: string;
    nodes: TemplateChildNode[];
    /** The blocks in the order of their start comments. */
    blocks: BlockNode[];
    /** Where each piece of markup starts in `html`. */
    markup: { offset: number; item: Markup }[];
}

// The comment of a block start holds the number of the block in `RenderedTemplate.blocks`.
const BLOCK_START = 'sw-block-start:';
const BLOCK_END = 'sw-block-end';

/**
 * @private
 *
 * Renders `tree`, the template of a component with the blocks it inherits and the core overrides applied.
 */
export function renderTemplate(tree: BlockContent[]): RenderedTemplate {
    const parts: string[] = [];
    const blocks: BlockNode[] = [];
    const markup: RenderedTemplate['markup'] = [];
    let offset = 0;
    const write = (text: string): void => {
        parts.push(text);
        offset += text.length;
    };
    const visit = (content: BlockContent[]): void => {
        content.forEach((item) => {
            if (isMarkup(item)) {
                markup.push({ offset, item });
                write(item.html);
            } else if (isBlock(item)) {
                write(`<!--${BLOCK_START}${blocks.length}-->`);
                blocks.push(item);
                visit(item.content);
                write(`<!--${BLOCK_END}-->`);
            }
        });
    };

    visit(tree);

    const html = parts.join('');

    return { html, nodes: parseTemplate(html), blocks, markup };
}

/**
 * @private
 *
 * What a comment of the rendered template marks: the block that starts there, the `end` of the innermost open
 * block, or `null` for a comment of the source.
 */
export function blockBoundary({ blocks }: RenderedTemplate, comment: string): BlockNode | 'end' | null {
    if (comment === BLOCK_END) {
        return 'end';
    }

    return comment.startsWith(BLOCK_START) ? blocks[Number(comment.slice(BLOCK_START.length))] : null;
}

/**
 * @private
 *
 * The line of the source file that the `offset` in the rendered HTML comes from.
 */
export function lineAt({ markup }: RenderedTemplate, offset: number): SourceLine {
    const piece = markup.findLast((candidate) => candidate.offset <= offset);

    if (piece === undefined) {
        throw new Error('An element of the merged template starts outside of its markup.');
    }

    const { file, line } = piece.item.at;

    return { file, line: line + piece.item.html.slice(0, offset - piece.offset).split('\n').length - 1 };
}

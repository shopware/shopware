/**
 * @sw-package framework
 */

/**
 * A comment left at the template root costs the component its element root.
 *
 * `{# … #}` renders nothing in twig, but the conversion turns it into an HTML comment, and a
 * development build keeps that comment as a second root node. The component's subtree is a fragment
 * from then on: attributes do still fall through, because Vue flags the shape `DEV_ROOT_FRAGMENT`,
 * but `$el` is the fragment's text anchor rather than an element — the same failure the single-root
 * reduction removes everywhere else, and the one `popover.directive.ts` throws on.
 *
 * Moving the comments into the block hands the root back and keeps each note next to the markup it
 * describes, because `reduceToSingleRoot()` skips an author's comment when it picks the block's
 * root. Only a template whose sole root is one converted block is touched: anything else renders a
 * fragment either way, so there is no root to restore.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { CommentNode, TemplateChildNode } from '@vue/compiler-dom';
import { isConvertedBlock, parseTemplate } from './template-ast';

/** An eslint directive is positional — moving it would silence a line it was never written for. */
const ESLINT_DIRECTIVE = /^\s*eslint-/;

type Edit = { start: number; end: number; text: string };

function isBlank(node: TemplateChildNode): boolean {
    return node.type === NodeTypes.TEXT && node.content.trim() === '';
}

/** The comment plus the line it owns, so removing it leaves no blank line behind. */
function removalRange(source: string, comment: CommentNode): Edit {
    const lineStart = source.lastIndexOf('\n', comment.loc.start.offset - 1) + 1;
    const ownsLine = source.slice(lineStart, comment.loc.start.offset).trim() === '';
    const end = comment.loc.end.offset;

    return {
        start: ownsLine ? lineStart : comment.loc.start.offset,
        end: ownsLine && source[end] === '\n' ? end + 1 : end,
        text: '',
    };
}

function moveRootCommentsIntoBlock(template: string): string {
    const root = parseTemplate(template);

    if (root === null) {
        return template;
    }

    const comments = root.children.filter((child): child is CommentNode => child.type === NodeTypes.COMMENT);
    const rendered = root.children.filter((child) => child.type !== NodeTypes.COMMENT && !isBlank(child));

    if (
        comments.length === 0 ||
        rendered.length !== 1 ||
        comments.some((comment) => ESLINT_DIRECTIVE.test(comment.content))
    ) {
        return template;
    }

    const [block] = rendered;

    // An empty block has no first child to insert before, and it renders an empty fragment whatever
    // happens to the comments, so there is nothing to gain.
    if (block.type !== NodeTypes.ELEMENT || !isConvertedBlock(block) || block.children.length === 0) {
        return template;
    }

    const insertAt = block.children[0].loc.start.offset;
    const moved = comments.map((comment) => template.slice(comment.loc.start.offset, comment.loc.end.offset)).join('\n');
    const edits: Edit[] = [
        { start: insertAt, end: insertAt, text: `${moved}\n` },
        ...comments.map((comment) => removalRange(template, comment)),
    ];

    // Applied back to front, so an earlier edit never invalidates a later one's offsets.
    return edits
        .sort((a, b) => b.start - a.start)
        .reduce((source, edit) => source.slice(0, edit.start) + edit.text + source.slice(edit.end), template);
}

export { moveRootCommentsIntoBlock };

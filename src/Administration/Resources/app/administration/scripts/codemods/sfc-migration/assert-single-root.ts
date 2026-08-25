/**
 * @sw-package framework
 */

/**
 * Did the block conversion cost the component its single root?
 *
 * A `{% block %}` emits no node of its own, so it never contributed a root. A `<sw-block>` does:
 * it is a component vnode, and two of them side by side make the converted component multi-root.
 * Vue then has no root element to apply a caller's fallthrough attributes to, and `$el` becomes the
 * fragment's text anchor rather than an element — which throws in every directive and caller that
 * measures it, `v-popover` and `v-tooltip` among them.
 *
 * Only the change matters. A template that was already multi-root loses nothing by the conversion
 * and is left alone; `<sw-block>` reduces content that really is single-rooted back to that one
 * node at runtime, so a single block around a single node keeps the root it had.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { RootNode, TemplateChildNode } from '@vue/compiler-dom';
import { isConvertedBlock, parseTemplate } from './template-ast';

const MULTI_ROOT =
    'the twig blocks make the component multi-root, so callers lose the attributes they pass and `$el` is no longer an element';

/** Whitespace and comments render no root: the production compiler drops comments outright. */
function isSignificant(node: RootNode | TemplateChildNode): boolean {
    if (node.type === NodeTypes.COMMENT) {
        return false;
    }

    return !(node.type === NodeTypes.TEXT && node.content.trim() === '');
}

function hasContinuationDirective(node: TemplateChildNode): boolean {
    return (
        node.type === NodeTypes.ELEMENT &&
        node.props.some((prop) => prop.type === NodeTypes.DIRECTIVE && (prop.name === 'else' || prop.name === 'else-if'))
    );
}

/**
 * How many roots this list renders. A `v-if` chain renders exactly one of its branches, so its
 * continuations belong to the branch they continue rather than counting for themselves.
 */
function rootCount(children: TemplateChildNode[]): number {
    let count = 0;

    for (const child of children.filter(isSignificant)) {
        if (count > 0 && hasContinuationDirective(child)) {
            continue;
        }

        count += 1;
    }

    return count;
}

/** The same list as the twig rendered it, with every converted block transparent again. */
function withoutBlocks(children: TemplateChildNode[]): TemplateChildNode[] {
    return children.flatMap((child) => {
        if (child.type === NodeTypes.ELEMENT && isConvertedBlock(child)) {
            return withoutBlocks(child.children);
        }

        return [child];
    });
}

/**
 * Returns one warning when the conversion turned a single-rooted component multi-root, otherwise
 * nothing. A warning rather than a blocker: the draft renders and behaves correctly on its own, it
 * is the callers that lose something, so the component is written with the note attached.
 *
 * The twig's own root tally is read from `converted`, before the cross-block conditional guards are
 * inserted — a guard is a root the conversion added, so counting it on both sides would hide
 * exactly the case it marks.
 */
function assertSingleRoot(converted: string, normalized: string): string[] {
    const convertedAst = parseTemplate(converted);
    const normalizedAst = parseTemplate(normalized);

    if (convertedAst === null || normalizedAst === null) {
        return [];
    }

    const before = rootCount(withoutBlocks(convertedAst.children));
    const after = rootCount(normalizedAst.children);

    return before === 1 && after > 1 ? [MULTI_ROOT] : [];
}

export { assertSingleRoot, MULTI_ROOT };

/**
 * @sw-package framework
 */

/**
 * Did the block conversion cost the component its single root? Two `<sw-block>` vnodes side by side
 * make it multi-root: fallthrough attributes are lost and `$el` becomes a text anchor, which throws
 * in `v-popover`, `v-tooltip` and every caller that measures it. A single block around a single node
 * keeps its root, since `<sw-block>` reduces single-rooted content back to that node at runtime.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { TemplateChildNode } from '@vue/compiler-dom';
import { isConvertedBlock, parseTemplate } from './template-ast';

const MULTI_ROOT =
    'the twig blocks make the component multi-root, so callers lose the attributes they pass and `$el` is no longer an element';

/** The production compiler drops comments outright. */
function isSignificant(node: TemplateChildNode): boolean {
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

/** A `v-if` chain renders one branch, so its continuations do not count. */
function rootCount(children: TemplateChildNode[]): number {
    return children.filter(isSignificant).filter((child) => !hasContinuationDirective(child)).length;
}

function withoutBlocks(children: TemplateChildNode[]): TemplateChildNode[] {
    return children.flatMap((child) => {
        if (child.type === NodeTypes.ELEMENT && isConvertedBlock(child)) {
            return withoutBlocks(child.children);
        }

        return [child];
    });
}

/**
 * A warning, not a blocker: the draft itself behaves, its callers lose something. The twig's tally
 * is read before the cross-block guards are inserted, which are roots the conversion added.
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

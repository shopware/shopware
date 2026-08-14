/**
 * @sw-package framework
 */

/**
 * What does a converted template look like to this codemod? The shared `@vue/compiler-dom` entry
 * point plus the `<sw-block>` shape predicate, so every pass inspecting the generated markup agrees
 * on what a block is and on what happens to markup Vue cannot parse.
 */

import { NodeTypes, parse } from '@vue/compiler-dom';
import type { ElementNode, RootNode } from '@vue/compiler-dom';

/**
 * Parses generated template markup, or returns `null` when Vue rejects it. Markup Vue cannot parse
 * is nothing these passes can inspect or repair; the validation gate reports it instead.
 */
function parseTemplate(source: string): RootNode | null {
    try {
        return parse(source, { comments: true });
    } catch {
        return null;
    }
}

/** Only the blocks this codemod emitted; a hand-written `<sw-block>` binds its name dynamically. */
function isConvertedBlock(node: ElementNode): boolean {
    return node.tag === 'sw-block' && node.props.some((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === 'name');
}

function elementChildren(node: ElementNode): ElementNode[] {
    return node.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
}

export { parseTemplate, isConvertedBlock, elementChildren };

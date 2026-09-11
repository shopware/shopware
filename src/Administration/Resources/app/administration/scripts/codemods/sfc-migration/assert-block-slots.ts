/** @sw-package framework */
import { ElementTypes, NodeTypes, type ElementNode, type TemplateChildNode } from '@vue/compiler-dom';
import { isConvertedBlock, parseTemplate } from './template-ast';

const SLOT_IN_BLOCK = 'slot definitions inside a Twig block need a receiving component';

/**
 * Structural blocks retain their original position around slot definitions. The setup compiler makes
 * them transparent to the receiver; moving a block inside a slot would change its extension contract.
 */
function assertBlockSlots(template: string): string[] {
    const ast = parseTemplate(template);
    if (!ast) return [];
    function hasOrphanSlot(nodes: TemplateChildNode[], owner: ElementNode | null, insideBlock: boolean): boolean {
        return nodes.some((node) => {
            if (node.type !== NodeTypes.ELEMENT) return false;
            const isSlot =
                node.tag === 'template' &&
                node.props.some((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === 'slot');
            if (insideBlock && isSlot && !owner) return true;
            if (isConvertedBlock(node)) return hasOrphanSlot(node.children, owner, true);
            return hasOrphanSlot(node.children, node.tagType === ElementTypes.COMPONENT ? node : null, false);
        });
    }
    return hasOrphanSlot(ast.children, null, false) ? [SLOT_IN_BLOCK] : [];
}

export { assertBlockSlots, SLOT_IN_BLOCK };

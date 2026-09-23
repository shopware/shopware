/**
 * @sw-package framework
 */

/**
 * Does a converted block swallow content? `<sw-block>` renders only its default slot, so a named slot
 * directly inside one is re-parented onto it and dropped — markup Vue compiles without complaint.
 * A default slot on `<sw-block>` is left to the build transform, which rejects it itself.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { ElementNode } from '@vue/compiler-dom';
import { elementChildren, isConvertedBlock, namedSlotName, parseTemplate } from './template-ast';

const SLOT_IN_BLOCK = 'named slot inside a twig block (<sw-block> renders only its default slot)';

function swallowsNamedSlot(nodes: ElementNode[]): boolean {
    for (const node of nodes) {
        const children = elementChildren(node);

        if (isConvertedBlock(node) && children.some((child) => namedSlotName(child) !== null)) {
            return true;
        }

        if (swallowsNamedSlot(children)) {
            return true;
        }
    }

    return false;
}

/** At most one blocker: nine swallowed slots are still one component in the report histogram. */
function assertBlockSlots(template: string): string[] {
    const ast = parseTemplate(template);

    if (ast === null) {
        return [];
    }

    const roots = ast.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);

    return swallowsNamedSlot(roots) ? [SLOT_IN_BLOCK] : [];
}

export { assertBlockSlots, SLOT_IN_BLOCK };

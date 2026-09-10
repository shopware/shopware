/**
 * @sw-package framework
 */

/**
 * Does a converted block swallow content? `<sw-block>` renders its default slot and nothing else
 * (see `src/app/component/structure/sw-block-override/sw-block`), while a `{% block %}` is
 * transparent. Wrapping a `<template #footer>` in one therefore re-parents that slot from the
 * surrounding component onto `<sw-block>`, which drops it — silently, and with markup Vue compiles
 * without complaint, so the validation gate cannot catch it.
 *
 * A default slot on a `<sw-block>` is not reported here: the build transform owns that rejection
 * and surfaces it through the validation gate, and reporting it twice would split the histogram.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode } from '@vue/compiler-dom';
import { elementChildren, isConvertedBlock, parseTemplate } from './template-ast';

const SLOT_IN_BLOCK = 'named slot inside a twig block (<sw-block> renders only its default slot)';

/**
 * A slot directive addressing anything but the default slot, in any authoring form (`#footer`,
 * `v-slot:footer`, `#[dynamic]`). A dynamic argument counts: it cannot be proven to be the default.
 */
function namedSlotDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find((prop): prop is DirectiveNode => {
        return (
            prop.type === NodeTypes.DIRECTIVE &&
            prop.name === 'slot' &&
            Boolean(prop.arg) &&
            !(prop.arg?.type === NodeTypes.SIMPLE_EXPRESSION && prop.arg.isStatic && prop.arg.content === 'default')
        );
    });
}

/** True when any converted block has a direct child addressing one of its named slots. */
function swallowsNamedSlot(nodes: ElementNode[]): boolean {
    for (const node of nodes) {
        const children = elementChildren(node);

        if (isConvertedBlock(node) && children.some((child) => namedSlotDirective(child) !== undefined)) {
            return true;
        }

        if (swallowsNamedSlot(children)) {
            return true;
        }
    }

    return false;
}

/**
 * Returns one blocker when the template loses content to a converted block, otherwise nothing.
 * At most one entry per template: a component with nine swallowed slots is still one component in
 * the report histogram.
 */
function assertBlockSlots(template: string): string[] {
    const ast = parseTemplate(template);

    if (ast === null) {
        return [];
    }

    const roots = ast.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);

    return swallowsNamedSlot(roots) ? [SLOT_IN_BLOCK] : [];
}

export { assertBlockSlots, SLOT_IN_BLOCK };

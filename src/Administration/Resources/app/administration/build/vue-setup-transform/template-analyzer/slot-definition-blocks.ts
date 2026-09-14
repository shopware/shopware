/** @sw-package framework */
import { ElementTypes, NodeTypes, type ElementNode, type TemplateChildNode } from '@vue/compiler-dom';
import { getStaticSwBlockName, type DirectiveNode, getForDirective } from './template-references';

/** @private */
export type SlotDefinitionGroup = { name: string; names: string[]; children: string[] };
/** @private */
export type SlotDefinitionReceiver = { at: number; groups: SlotDefinitionGroup[]; locals: string[] };

/** @private */
export function isSlotDefinitionBlock(node: ElementNode): boolean {
    return (
        node.tag === 'sw-block' &&
        node.children.some(
            (child) => child.type === NodeTypes.ELEMENT && (slotDirective(child) || isSlotDefinitionBlock(child)),
        )
    );
}

function slotDirective(node: ElementNode): DirectiveNode | undefined {
    if (node.tag !== 'template') return undefined;
    return node.props.find((prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && prop.name === 'slot');
}

function slotNameExpressions(nodes: TemplateChildNode[]): string[] {
    return nodes.flatMap((node) => {
        if (node.type === NodeTypes.TEXT) return node.content.trim() ? ["'default'"] : [];
        if (node.type === NodeTypes.INTERPOLATION) return ["'default'"];
        if (node.type !== NodeTypes.ELEMENT) return [];
        if (isSlotDefinitionBlock(node)) return slotNameExpressions(node.children);
        const directive = slotDirective(node);
        if (!directive) return node.type === NodeTypes.ELEMENT ? ["'default'"] : [];
        const name = !directive.arg
            ? "'default'"
            : directive.arg.isStatic
              ? JSON.stringify(directive.arg.content)
              : directive.arg.content;
        const loop = getForDirective(node)?.forParseResult;
        if (!loop) return [name];
        const parameters = [
            loop.value?.content ?? '_value',
            loop.key?.content ?? '_key',
            loop.index?.content ?? '_index',
        ];
        return [`...__swSetupSlotNames(${loop.source?.content ?? '[]'}, (${parameters.join(', ')}) => (${name}))`];
    });
}

/** @private */
export function describeSlotDefinitionBlock(node: ElementNode): SlotDefinitionGroup {
    return {
        name: getStaticSwBlockName(node)!,
        names: slotNameExpressions(node.children),
        children: node.children
            .filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT && isSlotDefinitionBlock(child))
            .map((child) => getStaticSwBlockName(child)!),
    };
}

/** @private */
export function canReceiveSlotDefinitions(node: ElementNode | undefined): node is ElementNode {
    return !!node && node.tagType === ElementTypes.COMPONENT && node.tag !== 'sw-block';
}

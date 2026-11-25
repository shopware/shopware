/**
 * @sw-package framework
 *
 * Parses a template whose Twig tags are HTML comments, and reads the nodes of the result.
 */
import { NodeTypes, parse } from '@vue/compiler-dom';
import type { AttributeNode, DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';

// The directives of a `v-if` chain, by the name Vue gives them.
const CHAIN_DIRECTIVES = [
    'if',
    'else-if',
    'else',
];

/** @private */
export function parseTemplate(html: string): TemplateChildNode[] {
    // The parser reports what Vue would reject at runtime. That is no concern of the snapshot.
    return parse(html, { comments: true, onError: () => {} }).children;
}

/**
 * @private
 *
 * The first directive of the element with one of the names, `slot` for `v-slot` and `#name`.
 */
export function directive(element: ElementNode, names: readonly string[]): DirectiveNode | undefined {
    return element.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && names.includes(prop.name),
    );
}

/** @private */
export function staticAttribute(element: ElementNode, name: string): string | undefined {
    return element.props.find((prop): prop is AttributeNode => prop.type === NodeTypes.ATTRIBUTE && prop.name === name)
        ?.value?.content;
}

/**
 * @private
 *
 * The text of a directive's value or argument. For a dynamic argument such as `#[name]` that is the expression.
 */
export function contentOf(expression: DirectiveNode['exp']): string | undefined {
    return expression?.type === NodeTypes.SIMPLE_EXPRESSION ? expression.content : undefined;
}

/** @private */
export function isWhitespace(node: TemplateChildNode): boolean {
    return node.type === NodeTypes.TEXT && node.content.trim() === '';
}

/**
 * @private
 *
 * An element of a `v-if` chain. The conditional slots of a component are a chain of their own (`ofSlots`): Vue
 * continues it with another `<template #slot>` only.
 */
export interface ChainMember {
    condition: DirectiveNode;
    ofSlots: boolean;
    continues: boolean;
}

/**
 * @private
 *
 * The chain member that `element` is, given the chain that is `open` before it. Vue attaches a `v-else-if` or
 * `v-else` to the element before it with only whitespace and comments in between: any other node, and a member
 * that `endsChain`, leaves no chain open.
 */
export function chainMember(element: ElementNode, open: ChainMember | null): ChainMember | null {
    const condition = directive(element, CHAIN_DIRECTIVES);

    if (condition === undefined) {
        return null;
    }

    const ofSlots = element.tag === 'template' && directive(element, ['slot']) !== undefined;

    return { condition, ofSlots, continues: condition.name !== 'if' && open?.ofSlots === ofSlots };
}

/** @private */
export function endsChain({ condition }: ChainMember): boolean {
    return condition.name === 'else';
}

/**
 * @sw-package framework
 *
 * Parses a template whose Twig tags are HTML comments, and reads the nodes of the result.
 */
import { NodeTypes, parse } from '@vue/compiler-dom';
import type { AttributeNode, DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';

/**
 * @private
 *
 * The directives of a `v-if` chain, by the name Vue gives them.
 */
export const CHAIN_DIRECTIVES = [
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
 * The text of a directive's value or argument. A dynamic argument such as `#[name]` has none.
 */
export function contentOf(expression: DirectiveNode['exp']): string | undefined {
    return expression?.type === NodeTypes.SIMPLE_EXPRESSION ? expression.content : undefined;
}

/** @private */
export function isWhitespace(node: TemplateChildNode): boolean {
    return node.type === NodeTypes.TEXT && node.content.trim() === '';
}

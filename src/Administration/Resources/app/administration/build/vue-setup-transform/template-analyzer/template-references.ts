/**
 * @sw-package framework
 */

/**
 * Identifies the sw-block elements and slot directives the template analyzers care about.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type {
    DirectiveNode as CoreDirectiveNode,
    ElementNode as CoreElementNode,
    TemplateChildNode,
} from '@vue/compiler-dom';

type DirectiveNode = CoreDirectiveNode & {
    arg?: { content: string; isStatic?: boolean };
    exp?: { content: string; loc: { start: { offset: number }; end: { offset: number } } };
    // Optional to match `@vue/compiler-core` (declares `rawName?: string`); the consumer in
    // sw-block-bindings falls back to `v-${name}`, and that fallback must stay reachable per the type.
    rawName?: string;
};

type ElementNode = CoreElementNode & {
    props: Array<CoreElementNode['props'][number] | DirectiveNode>;
    children: TemplateChildNode[];
};

/**
 * Checks whether a directive is the default slot shorthand/longhand.
 *
 */
function isDefaultSlotDirective(directive: DirectiveNode): boolean {
    return Boolean(
        directive.type === NodeTypes.DIRECTIVE &&
            directive.name === 'slot' &&
            (!directive.arg || (directive.arg.isStatic && directive.arg.content === 'default')),
    );
}

/**
 * Finds the default slot directive on an element.
 *
 */
function getDefaultSlotDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && isDefaultSlotDirective(prop as DirectiveNode),
    );
}

/**
 * Checks whether an element is a `<sw-block>` carrying the given identity attribute.
 *
 * Accepts the static form (`name="x"`) and the bound form (`:name="x"`); the bound form is rejected
 * later by `assertSwBlockAttributes`, but has to be recognised here so it reaches that check.
 */
function isSwBlockWithIdentity(node: TemplateChildNode, attribute: 'name' | 'extends'): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === attribute;
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === attribute;
    });
}

/**
 * Checks whether an element is an override block declaration (`<sw-block extends="...">`).
 */
function isSwBlockExtends(node: TemplateChildNode): node is ElementNode {
    return isSwBlockWithIdentity(node, 'extends');
}

/**
 * Checks whether an element is a base block declaration (`<sw-block name="...">`).
 */
function isSwBlockName(node: TemplateChildNode): node is ElementNode {
    return isSwBlockWithIdentity(node, 'name');
}

/**
 * Returns the static value of a `<sw-block>` identity attribute (`name` or `extends`), or null.
 *
 */
function getStaticSwBlockAttribute(node: ElementNode, attribute: 'name' | 'extends'): string | null {
    const identityAttribute = node.props.find(
        (prop): prop is Extract<ElementNode['props'][number], { type: NodeTypes.ATTRIBUTE }> =>
            prop.type === NodeTypes.ATTRIBUTE && prop.name === attribute,
    );

    return identityAttribute?.value?.content ?? null;
}

/**
 * Returns the static `name` of a base `<sw-block name="...">`, or null.
 */
function getStaticSwBlockName(node: ElementNode): string | null {
    return getStaticSwBlockAttribute(node, 'name');
}

/**
 * Returns the static `extends` of an override `<sw-block extends="...">`, or null.
 */
function getStaticSwBlockExtends(node: ElementNode): string | null {
    return getStaticSwBlockAttribute(node, 'extends');
}

/**
 * @private
 */
export {
    type DirectiveNode,
    type ElementNode,
    getDefaultSlotDirective,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
};

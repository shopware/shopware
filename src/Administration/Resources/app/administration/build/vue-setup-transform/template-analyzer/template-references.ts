/**
 * @sw-package framework
 */

/**
 * Walks the Vue template AST and tracks nested scopes.
 *
 * Slot scopes and v-for aliases introduce template-local names; these helpers collect the setup
 * references of whole subtrees while respecting that nesting, and identify the sw-block elements the
 * analyzers care about.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type {
    DirectiveNode as CoreDirectiveNode,
    ElementNode as CoreElementNode,
    TemplateChildNode,
} from '@vue/compiler-dom';
import {
    addPatternNames,
    collectExpressionReferences,
    collectExpressionWriteTargets,
    collectPatternReferences,
    parseBindingPattern,
} from '../flow-analysis';

/**
 * The setup references an override slot reads, plus the ones it writes to (assignment/update targets).
 */
type TemplateReferences = {
    references: Set<string>;
    /**
     * Write targets mapped to the template offset of the expression that writes them, so a rejection can
     * point at the author's `@click="count = 1"` rather than at the enclosing block.
     */
    writeTargets: Map<string, number>;
};

type DirectiveNode = CoreDirectiveNode & {
    arg?: { content: string; isStatic?: boolean };
    exp?: { content: string; loc: { start: { offset: number }; end: { offset: number } } };
    forParseResult?: {
        value?: { content: string };
        key?: { content: string };
        index?: { content: string };
        source?: { content: string };
    };
    // Optional to match `@vue/compiler-core` (declares `rawName?: string`); the consumer in
    // sw-block-bindings falls back to `v-${name}`, and that fallback must stay reachable per the type.
    rawName?: string;
};

type ElementNode = CoreElementNode & {
    props: Array<CoreElementNode['props'][number] | DirectiveNode>;
    children: TemplateChildNode[];
};

/**
 * Returns the pattern texts a directive contributes, in evaluation order.
 *
 * A v-slot contributes its single expression (`#default="{ item }"`); a v-for contributes its alias
 * slots (`(value, key, index)`) left to right, so an earlier alias shadows a later default.
 */
function getBindingPatternSources(directive: DirectiveNode | undefined): string[] {
    if (!directive) {
        return [];
    }

    if (directive.name === 'for') {
        const parseResult = directive.forParseResult;

        return [
            parseResult?.value,
            parseResult?.key,
            parseResult?.index,
        ]
            .map((expression) => expression?.content)
            .filter((content): content is string => Boolean(content));
    }

    return directive.exp?.content ? [directive.exp.content] : [];
}

/**
 * Returns the names a directive's binding patterns declare (v-slot props, v-for aliases).
 *
 * An unparseable v-for alias falls back to its raw text, which is what a plain `v-for="item in list"`
 * alias already looks like; an unparseable v-slot pattern is left to Vue's own parser to report.
 */
function collectBindingPatternNames(directive: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();

    getBindingPatternSources(directive).forEach((source) => {
        try {
            const { pattern } = parseBindingPattern(source);
            addPatternNames(pattern, scopeNames);
        } catch {
            if (directive?.name === 'for') {
                scopeNames.add(source);
            }
        }
    });

    return scopeNames;
}

/**
 * Returns the outer references a directive's binding patterns read.
 *
 * A pattern only reads through destructuring defaults (`{ a = fallback }`) and computed keys
 * (`{ [key]: v }`); the names it declares are handled by `collectBindingPatternNames`.
 */
function collectBindingPatternReferences(directive: DirectiveNode | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    getBindingPatternSources(directive).forEach((source) => {
        try {
            const { pattern } = parseBindingPattern(source);
            collectPatternReferences(pattern, [templateScope], references);
        } catch {
            // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
        }
    });

    return references;
}

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
 * Returns the static v-for directive on an element, when present.
 *
 */
function getForDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && (prop as DirectiveNode).name === 'for',
    );
}

/**
 * Collects references from one directive expression and dynamic argument.
 *
 */
function collectDirectiveReferences(directive: DirectiveNode, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    if (directive.name === 'slot') {
        if (directive.arg && !directive.arg.isStatic) {
            collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
        }

        return references;
    }

    if (directive.name === 'for') {
        collectExpressionReferences(directive.forParseResult?.source?.content, templateScope).forEach((name) =>
            references.add(name),
        );
        return references;
    }

    if (directive.arg && !directive.arg.isStatic) {
        collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
    }

    if (directive.exp?.content) {
        collectExpressionReferences(directive.exp.content, templateScope).forEach((name) => references.add(name));
        return references;
    }

    if (directive.name === 'bind' && directive.arg?.isStatic) {
        collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
    }

    return references;
}

/**
 * Collects references from descendants of a sw-block override slot.
 *
 */
function collectTemplateReferences(children: TemplateChildNode[], initialScope: Set<string>): TemplateReferences {
    const references = new Set<string>();
    const writeTargets = new Map<string, number>();

    function visit(node: TemplateChildNode, scope: Set<string>): void {
        if (node.type === NodeTypes.INTERPOLATION) {
            collectExpressionReferences((node.content as { content: string }).content, scope).forEach((name) =>
                references.add(name),
            );
            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        const forDirective = getForDirective(node);
        const childScope = new Set<string>(scope);
        const slotScopeNames = new Set<string>();

        if (forDirective) {
            collectDirectiveReferences(forDirective, scope).forEach((name) => references.add(name));
            collectBindingPatternReferences(forDirective, scope).forEach((name) => references.add(name));
            collectBindingPatternNames(forDirective).forEach((name) => childScope.add(name));
        }

        node.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === forDirective) {
                return;
            }

            const directive = prop as DirectiveNode;

            collectDirectiveReferences(directive, childScope).forEach((name) => references.add(name));

            // Assignment/update targets in a directive expression (e.g. `@click="count = count + 1"`).
            if (directive.exp?.content) {
                const expressionOffset = directive.exp.loc.start.offset;

                collectExpressionWriteTargets(directive.exp.content, childScope).forEach((name) => {
                    if (!writeTargets.has(name)) {
                        writeTargets.set(name, expressionOffset);
                    }
                });
            }

            // Any slot directive - default, named (#item), or dynamic (#[name]) - is handled the same:
            // its binding-pattern references (destructuring defaults, computed keys) are forwarded, or
            // they resolve against the hidden override component and silently break; and its scope names
            // shadow the slot content, so a `#item="{ info }"` with a setup binding `info` does not
            // over-forward the shadowed `info`.
            if (directive.name === 'slot') {
                collectBindingPatternReferences(directive, childScope).forEach((name) => references.add(name));
                collectBindingPatternNames(directive).forEach((name) => slotScopeNames.add(name));
            }
        });

        const scopedChildrenScope = new Set<string>(childScope);
        slotScopeNames.forEach((name) => scopedChildrenScope.add(name));

        node.children.forEach((child) => visit(child, scopedChildrenScope));
    }

    children.forEach((child) => visit(child, new Set<string>(initialScope)));

    return {
        references,
        writeTargets,
    };
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
    type TemplateReferences,
    collectTemplateReferences,
    getDefaultSlotDirective,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
};

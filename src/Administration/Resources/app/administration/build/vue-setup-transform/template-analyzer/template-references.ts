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
    writeTargets: Set<string>;
};

type TemplateEdit = {
    start: number;
    end: number;
    replacement: string;
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
    rawName: string;
};

type ElementNode = CoreElementNode & {
    props: Array<CoreElementNode['props'][number] | DirectiveNode>;
    children: TemplateChildNode[];
};

/**
 * Describes one generated slot-scope source entry.
 *
 * `sourceKey` is used to avoid duplicate insertion when a user already declared the same slot prop,
 * while `source` keeps the exact destructuring text that will be merged.
 */
type SlotMapping = {
    sourceKey: string;
    source: string;
};

/**
 * Returns names declared by a Vue v-slot expression.
 *
 */
function collectSlotScopeNames(slotDirective: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();

    if (!slotDirective?.exp?.content) {
        return scopeNames;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);
        addPatternNames(pattern, scopeNames);
    } catch {
        return scopeNames;
    }

    return scopeNames;
}

/**
 * Returns outer references used by a Vue v-slot binding pattern, such as destructuring defaults and computed keys.
 *
 */
function collectSlotScopeReferences(slotDirective: DirectiveNode | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    if (!slotDirective?.exp?.content) {
        return references;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);
        collectPatternReferences(pattern, [templateScope], references);
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
    }

    return references;
}

/**
 * Returns v-for aliases declared on an element.
 *
 */
function collectForScopeNames(forDirective: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();
    const parseResult = forDirective?.forParseResult;

    [
        parseResult?.value,
        parseResult?.key,
        parseResult?.index,
    ].forEach((expression) => {
        if (!expression?.content) {
            return;
        }

        try {
            const { pattern } = parseBindingPattern(expression.content);
            addPatternNames(pattern, scopeNames);
        } catch {
            scopeNames.add(expression.content);
        }
    });

    return scopeNames;
}

/**
 * Returns outer references used in v-for aliases, such as destructuring defaults and computed keys.
 *
 */
function collectForAliasReferences(forDirective: DirectiveNode | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();
    const parseResult = forDirective?.forParseResult;

    [
        parseResult?.value,
        parseResult?.key,
        parseResult?.index,
    ].forEach((expression) => {
        if (!expression?.content) {
            return;
        }

        try {
            const { pattern } = parseBindingPattern(expression.content);
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
    const writeTargets = new Set<string>();

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
            collectForAliasReferences(forDirective, scope).forEach((name) => references.add(name));
            collectForScopeNames(forDirective).forEach((name) => childScope.add(name));
        }

        node.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === forDirective) {
                return;
            }

            const directive = prop as DirectiveNode;

            collectDirectiveReferences(directive, childScope).forEach((name) => references.add(name));

            // Assignment/update targets in a directive expression (e.g. `@click="count = count + 1"`).
            if (directive.exp?.content) {
                collectExpressionWriteTargets(directive.exp.content, childScope).forEach((name) => writeTargets.add(name));
            }

            // Any slot directive - default, named (#item), or dynamic (#[name]) - is handled the same:
            // its binding-pattern references (destructuring defaults, computed keys) are forwarded, or
            // they resolve against the hidden override component and silently break; and its scope names
            // shadow the slot content, so a `#item="{ info }"` with a setup binding `info` does not
            // over-forward the shadowed `info`.
            if (directive.name === 'slot') {
                collectSlotScopeReferences(directive, childScope).forEach((name) => references.add(name));
                collectSlotScopeNames(directive).forEach((name) => slotScopeNames.add(name));
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
 * Checks whether an element is an override block declaration.
 *
 */
function isSwBlockExtends(node: TemplateChildNode): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'extends';
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === 'extends';
    });
}

/**
 * Checks whether an element is a base sw-block declaration.
 *
 */
function isSwBlockName(node: TemplateChildNode): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'name';
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === 'name';
    });
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

export {
    type DirectiveNode,
    type ElementNode,
    type SlotMapping,
    type TemplateEdit,
    type TemplateReferences,
    collectSlotScopeNames,
    collectSlotScopeReferences,
    collectTemplateReferences,
    getDefaultSlotDirective,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
};

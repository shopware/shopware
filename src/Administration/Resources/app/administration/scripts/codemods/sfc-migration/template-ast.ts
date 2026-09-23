/**
 * @sw-package framework
 */

/** The template queries every pass shares, so they agree on what a block is and what markup reads. */

import { ElementTypes, NodeTypes, isCoreComponent, parse, parserOptions } from '@vue/compiler-dom';
import type { ElementNode, ExpressionNode, RootNode, TemplateChildNode } from '@vue/compiler-dom';
import { camelize, capitalize } from 'vue';

/** `null` for markup Vue rejects, which the validation gate reports instead. */
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

const DYNAMIC_SLOT = '[dynamic]';

/**
 * The non-default slot a slot directive on `node` addresses (`#footer`, `v-slot:footer`), or null. A
 * dynamic argument cannot be proven to be the default, so it counts as named.
 */
function namedSlotName(node: ElementNode): string | null {
    for (const prop of node.props) {
        if (prop.type !== NodeTypes.DIRECTIVE || prop.name !== 'slot' || !prop.arg) {
            continue;
        }

        if (prop.arg.type === NodeTypes.SIMPLE_EXPRESSION && prop.arg.isStatic) {
            return prop.arg.content === 'default' ? null : prop.arg.content;
        }

        return DYNAMIC_SLOT;
    }

    return null;
}

function elementChildren(node: ElementNode): ElementNode[] {
    return node.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
}

const QUOTED_LITERAL = /'[^']*'|"[^"]*"|`[^`]*`/g;
// Not after a dot (`entity.name` yields `entity` only) nor inside an identifier.
const ROOT_IDENTIFIER = /(?<![.\w$])[A-Za-z_$][A-Za-z0-9_$]*/g;

function collectExpressionIdentifiers(expression: ExpressionNode, names: Set<string>): void {
    if (expression.type !== NodeTypes.SIMPLE_EXPRESSION || expression.isStatic) {
        return;
    }

    for (const match of expression.content.replace(QUOTED_LITERAL, '').matchAll(ROOT_IDENTIFIER)) {
        names.add(match[0]);
    }
}

function collectNodeIdentifiers(node: RootNode | TemplateChildNode, names: Set<string>): void {
    if (node.type === NodeTypes.INTERPOLATION) {
        collectExpressionIdentifiers(node.content, names);
        return;
    }

    if (node.type === NodeTypes.ELEMENT) {
        for (const prop of node.props) {
            if (prop.type !== NodeTypes.DIRECTIVE) {
                continue;
            }

            if (prop.exp) {
                collectExpressionIdentifiers(prop.exp, names);
            }

            if (prop.arg) {
                collectExpressionIdentifiers(prop.arg, names);
            }
        }
    }

    if (node.type === NodeTypes.ROOT || node.type === NodeTypes.ELEMENT) {
        for (const child of node.children) {
            collectNodeIdentifiers(child, names);
        }
    }
}

/**
 * The roots of all interpolation and directive expressions. Deliberately an over-approximation
 * (keywords, `v-for` locals): a name too many costs an unused binding or a refusal, a name too few
 * a template reading an undefined name.
 */
function collectTemplateIdentifiers(template: string): Set<string> {
    const names = new Set<string>();
    const root = parseTemplate(template);

    if (root !== null) {
        collectNodeIdentifiers(root, names);
    }

    return names;
}

/** Tags `resolveComponentType()` settles before the setup bindings, by Vue's own predicates. */
function resolvesBeforeSetupBindings(tag: string): boolean {
    return (
        tag === 'component' ||
        tag === 'Component' ||
        Boolean(isCoreComponent(tag)) ||
        Boolean(parserOptions.isBuiltInComponent?.(tag))
    );
}

function collectComponentTags(node: RootNode | TemplateChildNode, names: Set<string>): void {
    if (node.type === NodeTypes.ELEMENT) {
        // A plain HTML element never resolves against setup bindings.
        if (node.tagType === ElementTypes.COMPONENT && !resolvesBeforeSetupBindings(node.tag)) {
            // `resolveSetupReference()` tries the tag as written, camelized and capitalized.
            const camelName = camelize(node.tag);

            names.add(node.tag);
            names.add(camelName);
            names.add(capitalize(camelName));
        }
    }

    if (node.type === NodeTypes.ROOT || node.type === NodeTypes.ELEMENT) {
        for (const child of node.children) {
            collectComponentTags(child, names);
        }
    }
}

/**
 * Every binding name that would shadow a tag the template renders: a `<script setup>` template
 * prefers a setup binding over the registered component, so `<router-link>` next to a `routerLink`
 * prop renders nothing. The emitted `<sw-block>` tags are included.
 */
function collectTemplateComponentTags(template: string): Set<string> {
    const names = new Set<string>();
    const root = parseTemplate(template);

    if (root !== null) {
        collectComponentTags(root, names);
    }

    return names;
}

export {
    DYNAMIC_SLOT,
    parseTemplate,
    isConvertedBlock,
    namedSlotName,
    elementChildren,
    collectTemplateIdentifiers,
    collectTemplateComponentTags,
};

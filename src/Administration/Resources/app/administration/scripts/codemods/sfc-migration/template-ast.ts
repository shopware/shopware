/**
 * @sw-package framework
 */

/**
 * What does a converted template look like to this codemod? The shared `@vue/compiler-dom` entry
 * point, the `<sw-block>` shape predicate and the identifier scan of the template's expressions, so
 * every pass inspecting the generated markup agrees on what a block is, on which names the markup
 * reads, and on what happens to markup Vue cannot parse.
 */

import { NodeTypes, parse } from '@vue/compiler-dom';
import type { ElementNode, ExpressionNode, RootNode, TemplateChildNode } from '@vue/compiler-dom';

/**
 * Parses generated template markup, or returns `null` when Vue rejects it. Markup Vue cannot parse
 * is nothing these passes can inspect or repair; the validation gate reports it instead.
 */
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

function elementChildren(node: ElementNode): ElementNode[] {
    return node.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
}

const QUOTED_LITERAL = /'[^']*'|"[^"]*"|`[^`]*`/g;
// Not preceded by a dot, so `entity.name` contributes `entity` but never `name`, and not by a word
// character, so the tail of an identifier is never matched as one of its own.
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
 * Every name a converted template could resolve against a setup binding: the roots of all
 * interpolation and directive expressions.
 *
 * Deliberately an over-approximation — keywords, locals a `v-for` introduces and property names of
 * bracket access all end up in the set. It only ever decides whether a binding has to exist, or
 * whether renaming one would be visible from the template, so a name too many costs an unused
 * binding or a refusal, while a name too few would emit a template reading an undefined name.
 */
function collectTemplateIdentifiers(template: string): Set<string> {
    const names = new Set<string>();
    const root = parseTemplate(template);

    if (root !== null) {
        collectNodeIdentifiers(root, names);
    }

    return names;
}

export { parseTemplate, isConvertedBlock, elementChildren, collectTemplateIdentifiers };

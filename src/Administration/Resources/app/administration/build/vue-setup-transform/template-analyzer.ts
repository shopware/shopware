/**
 * @sw-package framework
 */

import { NodeTypes, parse as parseTemplate } from '@vue/compiler-dom';
import type { ElementNode as CoreElementNode } from '@vue/compiler-core';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';

type TemplateEdit = {
    start: number;
    end: number;
    replacement: string;
};

type ElementNode = CoreElementNode & {
    props: Array<
        CoreElementNode['props'][number] & {
            name?: string;
            arg?: { content: string; isStatic?: boolean };
        }
    >;
    children: CoreElementNode['children'];
};

type TemplateAnalysis = {
    edits: TemplateEdit[];
    privateBindings: Set<string>;
    privateNamespace: string | null;
};

function isSwBlockNode(node: ElementNode): boolean {
    return node.tag === 'sw-block';
}

function hasNameBinding(node: ElementNode): boolean {
    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'name';
        }

        return prop.name === 'bind' && prop.arg?.isStatic && prop.arg.content === 'name';
    });
}

function getStaticAttribute(node: ElementNode, name: string): string | null {
    const attribute = node.props.find((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === name);

    return attribute?.type === NodeTypes.ATTRIBUTE ? (attribute.value?.content ?? '') : null;
}

function hasDataBinding(node: ElementNode): boolean {
    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'data';
        }

        return prop.name === 'bind' && prop.arg?.isStatic && prop.arg.content === 'data';
    });
}

function findInsertOffset(node: ElementNode): number {
    return node.loc.start.offset + `<${node.tag}`.length;
}

function collectBaseTemplateEdits(node: ElementNode, edits: TemplateEdit[]): void {
    if (
        isSwBlockNode(node) &&
        (getStaticAttribute(node, 'name') !== null || hasNameBinding(node)) &&
        !hasDataBinding(node)
    ) {
        const offset = findInsertOffset(node);

        edits.push({
            start: offset,
            end: offset,
            replacement: ' :data="$dataScope"',
        });
    }

    node.children.forEach((child) => {
        if (child.type === NodeTypes.ELEMENT) {
            collectBaseTemplateEdits(child as ElementNode, edits);
        }
    });
}

function analyzeBaseTemplate(block: ShopwareSetupBlock): TemplateAnalysis {
    const edits: TemplateEdit[] = [];

    if (!block.template) {
        return {
            edits,
            privateBindings: new Set(),
            privateNamespace: null,
        };
    }

    const ast = parseTemplate(block.template.content, {
        comments: true,
    });

    ast.children.forEach((child) => {
        if (child.type === NodeTypes.ELEMENT) {
            collectBaseTemplateEdits(child as ElementNode, edits);
        }
    });

    return {
        edits: edits.map((edit) => ({
            ...edit,
            start: block.template!.contentStart + edit.start,
            end: block.template!.contentStart + edit.end,
        })),
        privateBindings: new Set(),
        privateNamespace: null,
    };
}

module.exports = {
    analyzeBaseTemplate,
};

export { type TemplateAnalysis, analyzeBaseTemplate };

/**
 * Twig is a line-oriented text format, not a language ts-morph understands.
 * Regex is the right tool here: every pattern (block tags, endblock, parent())
 * is a single fixed token that never nests inside JS expressions. Vue-specific
 * rewrites happen on the compiled template AST after that Twig pass.
 */

import {
    NodeTypes,
    parse,
    type DirectiveNode,
    type ElementNode,
    type RootNode,
    type TemplateChildNode,
} from '@vue/compiler-dom';

const EXTENDS_RE = /\{%\s*extends\s+["'][^"']+["']\s*%\}/;
const TWIG_COMMENT_RE = /\{#([\s\S]*?)#\}/g;
const ESLINT_DISABLE_TWIG = '<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->';
const BLOCK_START_LINE_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/;
const BLOCK_END_LINE_RE = /\{%\s*endblock(?:\s+\w+)?\s*%\}/;
const PARENT_LINE_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/;
const SW_BLOCK_TAG = 'sw-block';
const SW_BLOCK_PARENT_TAG = 'sw-block-parent';
const CONDITIONAL_DIRECTIVES = new Set([
    'if',
    'else-if',
    'else',
]);

type LegacyBlockHelperNames = {
    if: string;
    elseIf: string;
    else: string;
};

type TextEdit = {
    start: number;
    end: number;
    text: string;
};

const GLOBAL_LEGACY_HELPERS = {
    if: '$swLegacyBlockIf',
    elseIf: '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;

function isTwigBlockMigrationLine(line: string): boolean {
    return (
        EXTENDS_RE.test(line) || BLOCK_START_LINE_RE.test(line) || BLOCK_END_LINE_RE.test(line) || PARENT_LINE_RE.test(line)
    );
}

function getStaticAttribute(element: ElementNode, name: string): string | null {
    const attribute = element.props.find((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === name);

    return attribute?.type === NodeTypes.ATTRIBUTE ? (attribute.value?.content ?? null) : null;
}

function getDirective(element: ElementNode, name: string): DirectiveNode | null {
    const directive = element.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === name);

    return directive?.type === NodeTypes.DIRECTIVE ? directive : null;
}

function hasDirective(element: ElementNode, name: string): boolean {
    return getDirective(element, name) !== null;
}

function getDirectiveExpression(directive: DirectiveNode | null): string | null {
    if (!directive?.exp) {
        return null;
    }

    if (directive.exp.type === NodeTypes.SIMPLE_EXPRESSION) {
        return directive.exp.content;
    }

    return directive.exp.loc.source;
}

function getElementChildren(children: TemplateChildNode[]): ElementNode[] {
    return children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
}

function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function escapeDoubleQuotedAttribute(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function createLegacyHelperExpression(helperName: string, blockName: string, expression?: string | null): string {
    const escapedBlockName = escapeSingleQuotedString(blockName);

    if (!expression) {
        return `${helperName}('${escapedBlockName}')`;
    }

    return `${helperName}('${escapedBlockName}', ${expression})`;
}

function createConditionalReplacement(helperName: string, blockName: string, expression?: string | null): string {
    return `v-if="${escapeDoubleQuotedAttribute(createLegacyHelperExpression(helperName, blockName, expression))}"`;
}

function getTrailingConditionalChain(children: ElementNode[]): ElementNode[] {
    const lastElement = children.at(-1);

    if (!lastElement) {
        return [];
    }

    if (hasDirective(lastElement, 'if')) {
        return [lastElement];
    }

    if (!hasDirective(lastElement, 'else-if')) {
        return [];
    }

    const conditionalChain = [lastElement];

    for (let index = children.length - 2; index >= 0; index -= 1) {
        const child = children[index];

        if (hasDirective(child, 'else-if')) {
            conditionalChain.unshift(child);
            continue;
        }

        if (hasDirective(child, 'if')) {
            conditionalChain.unshift(child);

            return conditionalChain;
        }

        return [];
    }

    return [];
}

function getConditionalElementFollowingBlockParent(children: ElementNode[]): ElementNode | null {
    let shouldCheckChild = true;

    for (const child of children) {
        if (child.tag === SW_BLOCK_PARENT_TAG) {
            shouldCheckChild = true;
            continue;
        }

        if (shouldCheckChild && (hasDirective(child, 'else') || hasDirective(child, 'else-if'))) {
            return child;
        }

        shouldCheckChild = false;
    }

    return null;
}

function replaceDirective(
    edits: TextEdit[],
    directive: DirectiveNode | null,
    helperName: string,
    blockName: string,
    expression?: string | null,
): boolean {
    if (!directive) {
        return false;
    }

    edits.push({
        start: directive.loc.start.offset,
        end: directive.loc.end.offset,
        text: createConditionalReplacement(helperName, blockName, expression),
    });

    return true;
}

function collectTrailingConditionalEdits(
    edits: TextEdit[],
    blockName: string,
    conditionalChain: ElementNode[],
    helpers: LegacyBlockHelperNames,
): void {
    if (conditionalChain.length === 0) {
        return;
    }

    const firstDirective = getDirective(conditionalChain[0], 'if');
    const firstExpression = getDirectiveExpression(firstDirective);

    if (!firstExpression) {
        return;
    }

    replaceDirective(edits, firstDirective, helpers.if, blockName, firstExpression);

    conditionalChain.slice(1).forEach((conditionalElement) => {
        const directive = getDirective(conditionalElement, 'else-if');
        const expression = getDirectiveExpression(directive);

        if (!expression) {
            return;
        }

        replaceDirective(edits, directive, helpers.elseIf, blockName, expression);
    });
}

function collectLeadingConditionalEdit(
    edits: TextEdit[],
    blockName: string,
    conditionalElement: ElementNode | null,
    helpers: LegacyBlockHelperNames,
): void {
    if (!conditionalElement) {
        return;
    }

    const elseDirective = getDirective(conditionalElement, 'else');

    if (elseDirective) {
        replaceDirective(edits, elseDirective, helpers.else, blockName);

        return;
    }

    const elseIfDirective = getDirective(conditionalElement, 'else-if');
    const expression = getDirectiveExpression(elseIfDirective);

    if (!expression) {
        return;
    }

    replaceDirective(edits, elseIfDirective, helpers.elseIf, blockName, expression);
}

function walkElements(root: RootNode, visitor: (element: ElementNode) => void): void {
    const visit = (children: TemplateChildNode[]): void => {
        children.forEach((child) => {
            if (child.type !== NodeTypes.ELEMENT) {
                return;
            }

            visitor(child);
            visit(child.children);
        });
    };

    visit(root.children);
}

function applyTextEdits(source: string, edits: TextEdit[]): string {
    if (edits.length === 0) {
        return source;
    }

    return edits
        .sort((a, b) => b.start - a.start)
        .reduce((result, edit) => `${result.slice(0, edit.start)}${edit.text}${result.slice(edit.end)}`, source);
}

function transformLegacyBlockConditionals(
    template: string,
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): string {
    if (
        !template.includes('<sw-block') ||
        !Array.from(CONDITIONAL_DIRECTIVES).some((directive) => template.includes(`v-${directive}`))
    ) {
        return template;
    }

    const errors: unknown[] = [];
    const parsedTemplate = parse(template, {
        onError(error) {
            errors.push(error);
        },
    });

    const edits: TextEdit[] = [];

    walkElements(parsedTemplate, (element) => {
        if (element.tag !== SW_BLOCK_TAG) {
            return;
        }

        const blockName = getStaticAttribute(element, 'name') ?? getStaticAttribute(element, 'extends');

        if (!blockName) {
            return;
        }

        const children = getElementChildren(element.children);

        collectTrailingConditionalEdits(edits, blockName, getTrailingConditionalChain(children), helpers);
        collectLeadingConditionalEdit(edits, blockName, getConditionalElementFollowingBlockParent(children), helpers);
    });

    if (errors.length > 0 && edits.length === 0) {
        return template;
    }

    return applyTextEdits(template, edits);
}

/**
 * Converts a `.html.twig` file's content into a Vue `<template>` block.
 *
 * - `{% block name %}` → `<sw-block name="name" :data="$dataScope">`
 * - `{% endblock %}`  → `</sw-block>`
 * - `{{ parent() }}`  → `<sw-block-parent/>`
 * - `{% extends '…' %}` lines are removed entirely
 * - Accompanying eslint-disable-next-line comments are removed
 * - Plain HTML / Vue expressions pass through unchanged
 */
export function transformTemplate(twigContent: string): { template: string; useDataScope: boolean } {
    const BLOCK_START_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/g;
    const BLOCK_END_RE = /\{%\s*endblock(?:\s+\w+)?\s*%\}/g;
    const PARENT_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/g;

    const hasTwigBlocks = BLOCK_START_LINE_RE.test(twigContent);

    let body = twigContent;

    // Convert Twig comments to HTML comments regardless of block usage
    body = body.replace(TWIG_COMMENT_RE, (_, content) => `<!--${content}-->`);

    const cleanedLines = body.split('\n').filter((line, index, lines) => {
        if (EXTENDS_RE.test(line)) {
            return false;
        }

        const trimmed = line.trim();
        const nextLine = lines[index + 1] ?? '';
        const previousLine = lines[index - 1] ?? '';

        if (
            trimmed === ESLINT_DISABLE_TWIG &&
            (isTwigBlockMigrationLine(nextLine) || isTwigBlockMigrationLine(previousLine))
        ) {
            return false;
        }

        return true;
    });

    body = cleanedLines.join('\n');

    if (hasTwigBlocks) {
        body = body
            .split('\n')
            .map((line) => line.replace(BLOCK_START_RE, '<sw-block name="$1" :data="$dataScope">'))
            .map((line) => line.replace(BLOCK_END_RE, '</sw-block>'))
            .map((line) => line.replace(PARENT_RE, '<sw-block-parent/>'))
            .join('\n');

        body = transformLegacyBlockConditionals(body);
    }

    const transformed = `<template>\n${body}\n</template>`;
    const useDataScope = transformed.includes('$dataScope');
    return { template: transformed, useDataScope };
}

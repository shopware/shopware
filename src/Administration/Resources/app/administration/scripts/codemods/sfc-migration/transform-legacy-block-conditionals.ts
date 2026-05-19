import {
    NodeTypes,
    parse,
    type DirectiveNode,
    type ElementNode,
    type RootNode,
    type TemplateChildNode,
} from '@vue/compiler-dom';

const SW_BLOCK_TAG = 'sw-block';
const SW_BLOCK_PARENT_TAG = 'sw-block-parent';
const CONDITIONAL_DIRECTIVE_RE = /\bv-(?:if|else-if|else)\b/;

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

function getStaticAttribute(element: ElementNode, name: string): string | null {
    const attribute = element.props.find((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === name);

    return attribute?.type === NodeTypes.ATTRIBUTE ? (attribute.value?.content ?? null) : null;
}

function getDirective(element: ElementNode, name: string): DirectiveNode | null {
    const directive = element.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === name);

    return directive?.type === NodeTypes.DIRECTIVE ? directive : null;
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

    if (getDirective(lastElement, 'if')) {
        return [lastElement];
    }

    if (!getDirective(lastElement, 'else-if')) {
        return [];
    }

    const conditionalChain = [lastElement];

    for (let index = children.length - 2; index >= 0; index -= 1) {
        const child = children[index];

        if (getDirective(child, 'else-if')) {
            conditionalChain.unshift(child);
            continue;
        }

        if (getDirective(child, 'if')) {
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

        if (shouldCheckChild && (getDirective(child, 'else') || getDirective(child, 'else-if'))) {
            return child;
        }

        shouldCheckChild = false;
    }

    return null;
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

    if (!firstDirective || !firstExpression) {
        return;
    }

    edits.push({
        start: firstDirective.loc.start.offset,
        end: firstDirective.loc.end.offset,
        text: createConditionalReplacement(helpers.if, blockName, firstExpression),
    });

    conditionalChain.slice(1).forEach((conditionalElement) => {
        const directive = getDirective(conditionalElement, 'else-if');
        const expression = getDirectiveExpression(directive);

        if (!directive || !expression) {
            return;
        }

        edits.push({
            start: directive.loc.start.offset,
            end: directive.loc.end.offset,
            text: createConditionalReplacement(helpers.elseIf, blockName, expression),
        });
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
        edits.push({
            start: elseDirective.loc.start.offset,
            end: elseDirective.loc.end.offset,
            text: createConditionalReplacement(helpers.else, blockName),
        });

        return;
    }

    const elseIfDirective = getDirective(conditionalElement, 'else-if');
    const expression = getDirectiveExpression(elseIfDirective);

    if (!elseIfDirective || !expression) {
        return;
    }

    edits.push({
        start: elseIfDirective.loc.start.offset,
        end: elseIfDirective.loc.end.offset,
        text: createConditionalReplacement(helpers.elseIf, blockName, expression),
    });
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

export default function transformLegacyBlockConditionals(
    template: string,
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): string {
    if (!template.includes('<sw-block') || !CONDITIONAL_DIRECTIVE_RE.test(template)) {
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

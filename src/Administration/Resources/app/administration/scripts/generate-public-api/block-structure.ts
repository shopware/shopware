/**
 * @sw-package framework
 *
 * Reads what a block override depends on besides the block tree: the `v-if` chain a block follows, the template
 * variables it can use, and the elements and `v-if` chains that cross a block boundary. All of it is read from
 * the template a component renders after the merge, because that is the template Vue compiles.
 */
import { NodeTypes } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import ts from 'typescript';
import { isBlock, isMarkup } from './block-tree';
import type { BlockContent, BlockContext, BlockNode, Markup, ScopeVariable, SourceLine } from './block-tree';
import { byCodePoint } from './code-point-order';
import { BOTH_STATES, inBothStates, mergeStates } from './flag-states';
import type { FlagStates } from './flag-states';
import {
    chainMember,
    contentOf,
    directive,
    endsChain,
    isWhitespace,
    parseTemplate,
    staticAttribute,
} from './template-nodes';
import type { ChainMember } from './template-nodes';

/**
 * @private
 *
 * How the snapshot and the findings name the block of a crossing. `null` is the template outside of every block.
 */
export function blockLabel(block: string | null): string {
    return block ?? '(root)';
}

/** What crosses a block boundary: an element with its tag, or a `v-if` chain. */
type CrossingSubject = { kind: 'element'; tag: string } | { kind: 'chain' };

/**
 * @private
 *
 * An element that opens in one block and closes in another, or a `v-if` chain that continues in another block.
 * A block of `null` is the template outside of every block. `at` is the line of the element, for a chain that
 * of the member that continues it.
 */
export type Crossing = CrossingSubject & {
    opens: string | null;
    closes: string | null;
    states: FlagStates;
    at: SourceLine;
};

/**
 * @private
 *
 * What stands for the subject in the element id of a crossing: the tag of the element, or `v-if` for a chain.
 */
export function crossingSubject(crossing: CrossingSubject): string {
    return crossing.kind === 'chain' ? 'v-if' : crossing.tag;
}

/**
 * @private
 *
 * The part of the element id that names a crossing, `div@sw_card>sw_card_title`. Crossings are sorted by it.
 */
export function crossingId(crossing: Crossing): string {
    return `${crossingSubject(crossing)}@${blockLabel(crossing.opens)}>${blockLabel(crossing.closes)}`;
}

/** @private */
export interface TemplateStructure {
    contexts: ReadonlyMap<BlockNode, BlockContext>;
    /** One per `crossingId`, sorted by it. */
    crossings: Crossing[];
}

/** The template as HTML again, with a comment where a block starts and ends. */
interface RenderedTemplate {
    html: string;
    /** The blocks in the order of their start comments. */
    blocks: BlockNode[];
    /** Where each piece of markup starts in `html`. */
    markup: { offset: number; item: Markup }[];
}

/** The conditions of a `v-if` chain so far. `block` is the one its last `member` opens in. */
interface OpenChain {
    member: ChainMember;
    conditions: string[];
    block: BlockNode | null;
}

// The comment of a block start holds the number of the block in `RenderedTemplate.blocks`.
const BLOCK_START = 'sw-block-start:';
const BLOCK_END = 'sw-block-end';

function render(tree: BlockContent[]): RenderedTemplate {
    const parts: string[] = [];
    const rendered: RenderedTemplate = { html: '', blocks: [], markup: [] };
    let offset = 0;
    const write = (text: string): void => {
        parts.push(text);
        offset += text.length;
    };
    const visit = (content: BlockContent[]): void => {
        content.forEach((item) => {
            if (isMarkup(item)) {
                rendered.markup.push({ offset, item });
                write(item.html);
            } else if (isBlock(item)) {
                write(`<!--${BLOCK_START}${rendered.blocks.length}-->`);
                rendered.blocks.push(item);
                visit(item.content);
                write(`<!--${BLOCK_END}-->`);
            }
        });
    };

    visit(tree);

    return { ...rendered, html: parts.join('') };
}

function lineAt({ markup }: RenderedTemplate, offset: number): SourceLine {
    const piece = markup.findLast((candidate) => candidate.offset <= offset);

    if (piece === undefined) {
        throw new Error('An element of the merged template starts outside of its markup.');
    }

    const { file, line } = piece.item.at;

    return { file, line: line + piece.item.html.slice(0, offset - piece.offset).split('\n').length - 1 };
}

function onOneLine(expression: string): string {
    return expression.replace(/\s+/g, ' ').trim();
}

function bindingNames(name: ts.BindingName): string[] {
    return ts.isIdentifier(name)
        ? [name.text]
        : name.elements.flatMap((element) => (ts.isBindingElement(element) ? bindingNames(element.name) : []));
}

/** The names that `item`, `{ item, index }` or `[first, second]` declare. */
function declaredNames(pattern: string): string[] {
    const [statement] = ts.createSourceFile('scope.ts', `(${pattern}) => 0;`, ts.ScriptTarget.Latest, true).statements;
    const parameters =
        statement && ts.isExpressionStatement(statement) && ts.isArrowFunction(statement.expression)
            ? statement.expression.parameters
            : [];

    return parameters.flatMap((parameter) => bindingNames(parameter.name));
}

function loopVariables(element: ElementNode): ScopeVariable[] {
    const loop = directive(element, ['for'])?.forParseResult;

    if (loop === undefined) {
        return [];
    }

    const source = onOneLine(contentOf(loop.source) ?? '');

    return [
        loop.value,
        loop.key,
        loop.index,
    ]
        .flatMap((alias) => declaredNames(contentOf(alias) ?? ''))
        .map((name) => ({ name, kind: 'v-for', source }));
}

/** The name of the slot, or the expression of a dynamic name in brackets, as the template writes it. */
function slotName({ arg }: DirectiveNode): string | undefined {
    const isDynamic = arg?.type === NodeTypes.SIMPLE_EXPRESSION && !arg.isStatic;

    return isDynamic ? `[${arg.content}]` : contentOf(arg);
}

/** `owner` is the component the slot belongs to: the element itself, or the one around its `<template>`. */
function slotVariables(element: ElementNode, parentTag: string): ScopeVariable[] {
    const owner = element.tag === 'template' ? parentTag : element.tag;
    const slot = directive(element, ['slot']);
    const name = slot ? slotName(slot) : staticAttribute(element, 'slot');
    const props = slot ? contentOf(slot.exp) : staticAttribute(element, 'slot-scope');

    return declaredNames(props ?? '').map((variable) => ({
        name: variable,
        kind: 'slot',
        source: `${owner}#${name ?? 'default'}`,
    }));
}

function conditionsWith(condition: DirectiveNode, earlier: OpenChain | null): string[] {
    return [
        ...(earlier?.conditions ?? []),
        onOneLine(contentOf(condition.exp) ?? ''),
    ];
}

class StructureReader {
    private readonly contexts = new Map<BlockNode, BlockContext>();

    /** The open blocks that have no content yet. */
    private readonly unstarted = new Set<BlockNode>();

    private readonly crossings: Crossing[] = [];

    private readonly open: BlockNode[] = [];

    /** The variables of the elements around the current node, outermost first. */
    private readonly scopes: ScopeVariable[][] = [];

    public constructor(private readonly template: RenderedTemplate) {}

    public read(): TemplateStructure {
        this.visit(parseTemplate(this.template.html), '');

        return { contexts: this.contexts, crossings: mergeStates(this.crossings, crossingId) };
    }

    private visit(nodes: TemplateChildNode[], parentTag: string): void {
        let chain: OpenChain | null = null;

        nodes.forEach((node) => {
            if (node.type === NodeTypes.COMMENT) {
                this.visitComment(node.content, chain);
            } else if (node.type === NodeTypes.ELEMENT) {
                chain = this.continueChain(chain, node, this.startContent());
                this.visitElement(node, parentTag);
            } else if (!isWhitespace(node)) {
                chain = null;
                this.startContent();
            }
        });
    }

    private visitComment(content: string, chain: OpenChain | null): void {
        if (content.startsWith(BLOCK_START)) {
            this.openBlock(this.template.blocks[Number(content.slice(BLOCK_START.length))], chain);
        } else if (content === BLOCK_END) {
            this.closeBlock();
        }
    }

    private openBlock(block: BlockNode, chain: OpenChain | null): void {
        this.contexts.set(block, {
            afterIf: chain?.member.ofSlots === false ? chain.conditions : null,
            continuesChain: false,
            scope: this.scope(),
        });
        this.unstarted.add(block);
        this.open.push(block);
    }

    private closeBlock(): void {
        const closed = this.open.pop();

        if (closed !== undefined) {
            this.unstarted.delete(closed);
        }
    }

    /** The blocks whose content starts with the current node. */
    private startContent(): BlockNode[] {
        const blocks = [...this.unstarted];

        this.unstarted.clear();

        return blocks;
    }

    private visitElement(element: ElementNode, parentTag: string): void {
        const opens = this.currentBlock();

        this.scopes.push([
            ...loopVariables(element),
            ...slotVariables(element, parentTag),
        ]);
        this.visit(element.children, element.tag);
        this.scopes.pop();

        if (this.currentBlock() !== opens) {
            this.addCrossing({ kind: 'element', tag: element.tag }, opens, this.currentBlock(), element);
        }
    }

    /** The chain after `element`. Block starts and ends are comments, so they do not interrupt a chain. */
    private continueChain(chain: OpenChain | null, element: ElementNode, startsWithIt: BlockNode[]): OpenChain | null {
        const member = chainMember(element, chain?.member ?? null);

        if (member === null) {
            return null;
        }

        const earlier = member.continues ? chain : null;

        if (earlier !== null) {
            this.joinChain(earlier, element, startsWithIt);
        }

        return endsChain(member)
            ? null
            : { member, conditions: conditionsWith(member.condition, earlier), block: this.currentBlock() };
    }

    /**
     * What a member that continues `earlier` means for the blocks: the chain crosses a block boundary when the
     * block of the member before it is closed, and the blocks that start with the member continue the chain.
     */
    private joinChain(earlier: OpenChain, member: ElementNode, startsWithIt: BlockNode[]): void {
        if (this.hasLeft(earlier.block)) {
            this.addCrossing({ kind: 'chain' }, earlier.block, this.currentBlock(), member);
        }

        if (!earlier.member.ofSlots) {
            startsWithIt.forEach((block) => this.continueChainIn(block));
        }
    }

    private continueChainIn(block: BlockNode): void {
        const context = this.contexts.get(block);

        if (context !== undefined) {
            this.contexts.set(block, { ...context, continuesChain: true });
        }
    }

    /** A block that is still open holds the current place, and takes it along when it is replaced. */
    private hasLeft(block: BlockNode | null): boolean {
        return block !== null && !this.open.includes(block);
    }

    private currentBlock(): BlockNode | null {
        return this.open.at(-1) ?? null;
    }

    /** An inner variable hides an outer one of the same name. */
    private scope(): ScopeVariable[] {
        const variables = new Map(this.scopes.flat().map((variable) => [variable.name, variable]));

        return [...variables.values()].sort((a, b) => byCodePoint(a.name, b.name));
    }

    private addCrossing(subject: CrossingSubject, opens: BlockNode | null, closes: BlockNode | null, at: ElementNode): void {
        this.crossings.push({
            ...subject,
            opens: opens?.name ?? null,
            closes: closes?.name ?? null,
            states: inBothStates(opens?.states ?? BOTH_STATES, closes?.states ?? BOTH_STATES),
            at: lineAt(this.template, at.loc.start.offset),
        });
    }
}

/**
 * @private
 *
 * The context of every block of `tree` and the crossings between its blocks. `tree` is the template a component
 * renders, with the blocks it inherits and the core overrides applied.
 */
export function readStructure(tree: BlockContent[]): TemplateStructure {
    return new StructureReader(render(tree)).read();
}

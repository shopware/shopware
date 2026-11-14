/**
 * @sw-package framework
 *
 * Reads the blocks of a template: Twig blocks in a `.html.twig` file and `<sw-block>` elements in a `.vue` file.
 * Both end up in one HTML tree, because the flag state of a block depends on the `v-if` chains around it.
 */
import { NodeTypes, parse } from '@vue/compiler-dom';
import type { AttributeNode, DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { byCodePoint } from './code-point-order';
import {
    BOTH_STATES,
    ConditionChain,
    expressionTruths,
    FLAG_STATES,
    inAnyState,
    inBothStates,
    perState,
} from './flag-states';
import type { FlagContext, FlagStates } from './flag-states';

/**
 * @private
 *
 * A block under one parent block. A block that sits under another parent in the major state has two entries.
 */
export interface TemplateBlock {
    name: string;
    parent: string | null;
    states: FlagStates;
}

const TWIG_TAG = /\{#[\s\S]*?#\}|\{%\s*block\s+([^%\s}]+)\s*%\}|\{%\s*endblock\s*%\}/g;

// Twig block tags become HTML comments, so the HTML parser keeps their position in the tree.
const BLOCK_START = 'sw-twig-block:';
const BLOCK_END = 'sw-twig-endblock';

const NATIVE_BLOCK_TAG = 'sw-block';

interface OpenBlock {
    name: string;
    states: FlagStates;
}

function templateOf(source: string, file: string): string {
    return file.endsWith('.vue') ? (parseSfc(source, { filename: file }).descriptor.template?.content ?? '') : source;
}

function staticAttribute(element: ElementNode, name: string): string | undefined {
    return element.props.find((prop): prop is AttributeNode => prop.type === NodeTypes.ATTRIBUTE && prop.name === name)
        ?.value?.content;
}

function conditionOf(element: ElementNode): DirectiveNode | undefined {
    return element.props.find(
        (prop): prop is DirectiveNode =>
            prop.type === NodeTypes.DIRECTIVE &&
            [
                'if',
                'else-if',
                'else',
            ].includes(prop.name),
    );
}

function expressionOf(directive: DirectiveNode): string {
    return directive.exp?.type === NodeTypes.SIMPLE_EXPRESSION ? directive.exp.content : '';
}

function isWhitespace(node: TemplateChildNode): boolean {
    return node.type === NodeTypes.TEXT && node.content.trim() === '';
}

class BlockReader {
    private readonly open: OpenBlock[] = [];

    private readonly blocks = new Map<string, TemplateBlock>();

    private twigTagsSeen = 0;

    private closedTooOften = false;

    public constructor(
        private readonly file: string,
        private readonly flags: FlagContext,
    ) {}

    public read(nodes: TemplateChildNode[], twigTags: number): TemplateBlock[] {
        this.visit(nodes, BOTH_STATES);

        if (this.twigTagsSeen !== twigTags) {
            throw new Error(
                `${this.file}: a {% block %} tag sits where the HTML parser cannot see it, ` +
                    'for example inside a tag, a <script>, a <style> or a <textarea>.',
            );
        }

        if (this.closedTooOften) {
            throw new Error(`${this.file}: an {% endblock %} closes a block that was never opened.`);
        }

        if (this.open.length > 0) {
            throw new Error(`${this.file}: the block ${this.open[this.open.length - 1].name} is never closed.`);
        }

        return [...this.blocks.values()].sort(
            (a, b) => byCodePoint(a.name, b.name) || byCodePoint(a.parent ?? '', b.parent ?? ''),
        );
    }

    private visit(nodes: TemplateChildNode[], states: FlagStates): void {
        let chain: ConditionChain | null = null;

        nodes.forEach((node) => {
            if (node.type === NodeTypes.COMMENT) {
                this.visitComment(node.content, states);

                return;
            }

            if (node.type !== NodeTypes.ELEMENT) {
                chain = isWhitespace(node) ? chain : null;

                return;
            }

            const condition = conditionOf(node);

            if (!condition) {
                chain = null;
                this.visitElement(node, states);

                return;
            }

            if (condition.name === 'if' || chain === null) {
                chain = new ConditionChain();
            }

            this.visitElement(node, inBothStates(states, this.branchStates(chain, condition)));
        });
    }

    private branchStates(chain: ConditionChain, condition: DirectiveNode): FlagStates {
        return condition.name === 'else'
            ? chain.elseBranch()
            : chain.branch(expressionTruths(expressionOf(condition), this.flags));
    }

    private visitComment(content: string, states: FlagStates): void {
        if (content.startsWith(BLOCK_START)) {
            this.twigTagsSeen += 1;
            this.openBlock(content.slice(BLOCK_START.length), states);
        } else if (content === BLOCK_END) {
            this.twigTagsSeen += 1;
            this.closeBlock();
        }
    }

    private visitElement(element: ElementNode, states: FlagStates): void {
        // A bound `:name` carries no literal block name. An `extends` names the block an override replaces.
        const nativeBlock =
            element.tag === NATIVE_BLOCK_TAG
                ? (staticAttribute(element, 'name') ?? staticAttribute(element, 'extends'))
                : undefined;

        if (nativeBlock === undefined) {
            this.visit(element.children, states);

            return;
        }

        this.openBlock(nativeBlock, states);
        this.visit(element.children, states);
        this.closeBlock();
    }

    private openBlock(name: string, states: FlagStates): void {
        FLAG_STATES.filter((state) => states[state]).forEach((state) => {
            const parent = this.open.findLast((block) => block.states[state])?.name ?? null;

            this.add(
                name,
                parent,
                perState((other) => other === state),
            );
        });

        this.open.push({ name, states });
    }

    private closeBlock(): void {
        this.closedTooOften ||= this.open.pop() === undefined;
    }

    private add(name: string, parent: string | null, states: FlagStates): void {
        const key = `${name}\n${parent ?? ''}`;
        const known = this.blocks.get(key);

        this.blocks.set(key, { name, parent, states: known ? inAnyState(known.states, states) : states });
    }
}

/**
 * @private
 *
 * The blocks of the template in `file`, sorted by name and parent.
 */
export function templateBlocks(source: string, file: string, flags: FlagContext): TemplateBlock[] {
    let twigTags = 0;
    const html = templateOf(source, file).replace(TWIG_TAG, (tag: string, name?: string) => {
        if (tag.startsWith('{#')) {
            return '';
        }

        twigTags += 1;

        return name === undefined ? `<!--${BLOCK_END}-->` : `<!--${BLOCK_START}${name}-->`;
    });
    // The parser reports what Vue would reject at runtime. That is no concern of the snapshot.
    const { children } = parse(html, { comments: true, onError: () => {} });

    return new BlockReader(file, flags).read(children, twigTags);
}

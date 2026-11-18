/**
 * @sw-package framework
 *
 * Reads the blocks of a template: Twig blocks in a `.html.twig` file and `<sw-block>` elements in a `.vue` file.
 * Both end up in one HTML tree, because the flag state of a block depends on the `v-if` chains around it.
 */
import { NodeTypes, parse } from '@vue/compiler-dom';
import type { AttributeNode, DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { parse as parseSfc } from '@vue/compiler-sfc';
import type { BlockContent, BlockNode, Placement } from './block-tree';
import { BOTH_STATES, ConditionChain, expressionTruths, inBothStates } from './flag-states';
import type { FlagContext, FlagStates } from './flag-states';

// The runtime reads every tag that starts with `parent` as `{% parent %}`.
const TWIG_TAG = /\{#[\s\S]*?#\}|\{%\s*block\s+([^%\s}]+)\s*%\}|\{%\s*(endblock)\s*%\}|\{%\s*parent\b[^%]*%\}/g;

// Twig tags become HTML comments, so the HTML parser keeps their position in the tree.
const BLOCK_START = 'sw-twig-block:';
const BLOCK_END = 'sw-twig-endblock';
const PARENT_CALL = 'sw-twig-parent';

const NATIVE_BLOCK_TAG = 'sw-block';

interface OpenBlock {
    block: BlockNode;
    /** How many conditional elements were open around the place where the block opened. */
    depth: number;
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
    private readonly tree: BlockContent[] = [];

    private readonly open: OpenBlock[] = [];

    /** The flag states of the conditional elements around the current node, outermost first. */
    private readonly branches: FlagStates[] = [];

    private twigTagsSeen = 0;

    private closedTooOften = false;

    public constructor(
        private readonly file: string,
        private readonly flags: FlagContext,
    ) {}

    public read(nodes: TemplateChildNode[], twigTags: number): BlockContent[] {
        this.visit(nodes);

        if (this.twigTagsSeen !== twigTags) {
            throw new Error(
                `${this.file}: a {% block %} or {% parent %} tag sits where the HTML parser cannot see it, ` +
                    'for example inside a tag, a <script>, a <style> or a <textarea>.',
            );
        }

        if (this.closedTooOften) {
            throw new Error(`${this.file}: an {% endblock %} closes a block that was never opened.`);
        }

        if (this.open.length > 0) {
            throw new Error(`${this.file}: the block ${this.open[this.open.length - 1].block.name} is never closed.`);
        }

        return this.tree;
    }

    private visit(nodes: TemplateChildNode[]): void {
        let chain: ConditionChain | null = null;

        nodes.forEach((node) => {
            if (node.type === NodeTypes.COMMENT) {
                this.visitComment(node.content);

                return;
            }

            if (node.type !== NodeTypes.ELEMENT) {
                chain = isWhitespace(node) ? chain : null;

                return;
            }

            const condition = conditionOf(node);

            if (!condition) {
                chain = null;
                this.visitElement(node);

                return;
            }

            if (condition.name === 'if' || chain === null) {
                chain = new ConditionChain();
            }

            this.branches.push(this.branchStates(chain, condition));
            this.visitElement(node);
            this.branches.pop();
        });
    }

    private branchStates(chain: ConditionChain, condition: DirectiveNode): FlagStates {
        return condition.name === 'else'
            ? chain.elseBranch()
            : chain.branch(expressionTruths(expressionOf(condition), this.flags));
    }

    private visitComment(content: string): void {
        if (content.startsWith(BLOCK_START)) {
            this.twigTagsSeen += 1;
            this.openBlock(content.slice(BLOCK_START.length));
        } else if (content === BLOCK_END) {
            this.twigTagsSeen += 1;
            this.closeBlock();
        } else if (content === PARENT_CALL) {
            this.twigTagsSeen += 1;
            this.add({ kind: 'parent', ...this.place() });
        }
    }

    private visitElement(element: ElementNode): void {
        // A bound `:name` carries no literal block name. An `extends` names the block an override replaces.
        const nativeBlock =
            element.tag === NATIVE_BLOCK_TAG
                ? (staticAttribute(element, 'name') ?? staticAttribute(element, 'extends'))
                : undefined;

        if (nativeBlock === undefined) {
            this.visit(element.children);

            return;
        }

        this.openBlock(nativeBlock);
        this.visit(element.children);
        this.closeBlock();
    }

    /** `within` counts the conditional elements that opened after the block around the current node. */
    private place(): Placement {
        const inBlock = this.branches.slice(this.open.at(-1)?.depth ?? 0);

        return {
            within: inBlock.reduce(inBothStates, BOTH_STATES),
            states: this.branches.reduce(inBothStates, BOTH_STATES),
        };
    }

    private openBlock(name: string): void {
        const block: BlockNode = { kind: 'block', name, origin: null, content: [], ...this.place() };

        this.add(block);
        this.open.push({ block, depth: this.branches.length });
    }

    private closeBlock(): void {
        this.closedTooOften ||= this.open.pop() === undefined;
    }

    private add(item: BlockContent): void {
        (this.open.at(-1)?.block.content ?? this.tree).push(item);
    }
}

/**
 * @private
 *
 * The blocks and `{% parent %}` tags of the template in `file`, in the order of the source.
 */
export function templateTree(source: string, file: string, flags: FlagContext): BlockContent[] {
    let twigTags = 0;
    const html = templateOf(source, file).replace(TWIG_TAG, (tag: string, name?: string, end?: string) => {
        if (tag.startsWith('{#')) {
            return '';
        }

        twigTags += 1;

        if (name !== undefined) {
            return `<!--${BLOCK_START}${name}-->`;
        }

        return `<!--${end === undefined ? PARENT_CALL : BLOCK_END}-->`;
    });
    // The parser reports what Vue would reject at runtime. That is no concern of the snapshot.
    const { children } = parse(html, { comments: true, onError: () => {} });

    return new BlockReader(file, flags).read(children, twigTags);
}

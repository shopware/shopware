/**
 * @sw-package framework
 *
 * Reads the blocks of a template: Twig blocks in a `.html.twig` file and `<sw-block>` elements in a `.vue` file.
 * Both end up in one HTML tree, because the flag state of a block depends on the `v-if` chains around it.
 * The HTML between the blocks is kept as markup, so the template can be put together again after the merge.
 */
import { NodeTypes } from '@vue/compiler-dom';
import type { CommentNode, DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { parseTemplateMarkers } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { BOTH_STATES, inBothStates } from '../../model/flag-states';
import type { FlagContext, FlagStates } from '../../model/flag-states';
import type { SourceLine } from '../../model/source-line';
import { ConditionChain, expressionTruths } from '../flag-conditions';
import type { BlockContent, BlockNode, Markup, Placement } from './block-tree';
import { chainMember, contentOf, endsChain, isWhitespace, parseTemplate, staticAttribute } from './template-nodes';
import type { ChainMember } from './template-nodes';

// The runtime reads every tag that starts with `parent` as `{% parent %}`.
const TWIG_TAG = /\{#[\s\S]*?#\}|\{%\s*block\s+([^%\s}]+)\s*%\}|\{%\s*(endblock)\s*%\}|\{%\s*parent\b[^%]*%\}/g;

// Twig tags become HTML comments, so the HTML parser keeps their position in the tree. The comment of a block
// start holds the name of the block and its number among the `{% block %}` tags: `sw-twig-block:sw_card 0`.
const BLOCK_START = 'sw-twig-block:';
const BLOCK_END = 'sw-twig-endblock';
const PARENT_CALL = 'sw-twig-parent';

const NATIVE_BLOCK_TAG = 'sw-block';

interface OpenBlock {
    block: BlockNode;
    /** How many conditional elements were open around the place where the block opened. */
    depth: number;
}

/** The template with the line of the file it starts on. */
interface TemplateText {
    text: string;
    firstLine: number;
}

/** A template as the HTML parser gets it, with its Twig tags as comments. */
interface TemplateHtml extends TemplateText {
    file: string;
    /** How many `{% block %}`, `{% endblock %}` and `{% parent %}` tags the text holds as comments. */
    twigTags: number;
    /** The markers before each `{% block %}` tag, in the order of the tags. */
    blockMarkers: Marker[][];
}

function templateOf(source: string, file: string): TemplateText {
    if (!file.endsWith('.vue')) {
        return { text: source, firstLine: 1 };
    }

    const template = parseSfc(source, { filename: file }).descriptor.template;

    return { text: template?.content ?? '', firstLine: template?.loc.start.line ?? 1 };
}

function lineBreaksOf(text: string): string {
    return text.replace(/[^\n]/g, '');
}

function markersOf(comments: string[]): Marker[] {
    return comments.flatMap(parseTemplateMarkers).flatMap((match) => ('marker' in match ? [match.marker] : []));
}

/** Where the content of an element starts and ends. A self-closing element has none. */
function contentRange(element: ElementNode, html: string): [number, number] {
    const end = element.loc.end.offset;

    if (element.isSelfClosing) {
        return [end, end];
    }

    const afterProps = Math.max(element.loc.start.offset, ...element.props.map((prop) => prop.loc.end.offset));
    const start = html.indexOf('>', afterProps) + 1;

    return [start, Math.max(start, html.lastIndexOf('</', end))];
}

/**
 * Replaces the Twig tags of a template. Every replacement keeps the line breaks of its tag, so the lines of the
 * result are those of the template. A marker belongs to a block when only whitespace and other Twig comments
 * stand between its comment and the `{% block %}` tag.
 */
function twigToHtml({ text, firstLine }: TemplateText, file: string): TemplateHtml {
    const blockMarkers: Marker[][] = [];
    let twigTags = 0;
    let comments: string[] = [];
    let commentsEnd = 0;
    const html = text.replace(TWIG_TAG, (tag: string, name: string | undefined, end: string | undefined, offset: number) => {
        const leading = text.slice(commentsEnd, offset).trim() === '' ? comments : [];

        comments = tag.startsWith('{#') ? [...leading, tag] : [];
        commentsEnd = offset + tag.length;

        if (tag.startsWith('{#')) {
            return lineBreaksOf(tag);
        }

        twigTags += 1;

        if (name === undefined) {
            return `<!--${end === undefined ? PARENT_CALL : BLOCK_END}-->${lineBreaksOf(tag)}`;
        }

        blockMarkers.push(markersOf(leading));

        return `<!--${BLOCK_START}${name} ${blockMarkers.length - 1}-->${lineBreaksOf(tag)}`;
    });

    return { text: html, firstLine, file, twigTags, blockMarkers };
}

/** Cuts the HTML between the Twig tags into pieces of markup, from the start of the template to its end. */
class MarkupCursor {
    private offset = 0;

    private line: number;

    public constructor(private readonly template: TemplateHtml) {
        this.line = template.firstLine;
    }

    /** The HTML up to `end`, if there is any. The cursor continues at `continueAt`, behind a Twig tag. */
    public take(end: number, continueAt = end): Markup[] {
        const { text, file } = this.template;
        const html = text.slice(this.offset, end);
        const at = { file, line: this.line };

        this.line += lineBreaksOf(text.slice(this.offset, continueAt)).length;
        this.offset = continueAt;

        return html === '' ? [] : [{ kind: 'markup', html, at }];
    }
}

class BlockReader {
    private readonly tree: BlockContent[] = [];

    private readonly open: OpenBlock[] = [];

    /** The flag states of the conditional elements around the current node, outermost first. */
    private readonly branches: FlagStates[] = [];

    private twigTagsSeen = 0;

    private closedTooOften = false;

    private readonly markup: MarkupCursor;

    private readonly file: string;

    public constructor(
        private readonly template: TemplateHtml,
        private readonly flags: FlagContext,
    ) {
        this.markup = new MarkupCursor(template);
        this.file = template.file;
    }

    public read(): BlockContent[] {
        this.visit(parseTemplate(this.template.text));
        this.addMarkup(this.template.text.length);

        if (this.twigTagsSeen !== this.template.twigTags) {
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
        let open: { member: ChainMember; chain: ConditionChain } | null = null;

        nodes.forEach((node) => {
            if (node.type === NodeTypes.COMMENT) {
                this.visitComment(node);

                return;
            }

            if (node.type !== NodeTypes.ELEMENT) {
                open = isWhitespace(node) ? open : null;

                return;
            }

            const member = chainMember(node, open?.member ?? null);

            if (member === null) {
                open = null;
                this.visitElement(node);

                return;
            }

            const chain = member.continues && open !== null ? open.chain : new ConditionChain();

            open = endsChain(member) ? null : { member, chain };
            this.branches.push(this.branchStates(chain, member.condition));
            this.visitElement(node);
            this.branches.pop();
        });
    }

    private branchStates(chain: ConditionChain, condition: DirectiveNode): FlagStates {
        return condition.name === 'else'
            ? chain.elseBranch()
            : chain.branch(expressionTruths(contentOf(condition.exp) ?? '', this.flags));
    }

    private visitComment({ content, loc }: CommentNode): void {
        const isTwigTag = content.startsWith(BLOCK_START) || content === BLOCK_END || content === PARENT_CALL;

        if (!isTwigTag) {
            return;
        }

        this.addMarkup(loc.start.offset, loc.end.offset);
        this.twigTagsSeen += 1;

        if (content.startsWith(BLOCK_START)) {
            const [name, number] = content.slice(BLOCK_START.length).split(' ');

            this.openBlock(name, this.lineOf(loc.start.line), this.template.blockMarkers[Number(number)]);
        } else if (content === BLOCK_END) {
            this.closeBlock();
        } else {
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

        const [contentStart, contentEnd] = contentRange(element, this.template.text);

        this.addMarkup(contentStart);
        this.openBlock(nativeBlock, this.lineOf(element.loc.start.line), []);
        this.visit(element.children);
        this.addMarkup(contentEnd);
        this.closeBlock();
    }

    private lineOf(lineOfTemplate: number): SourceLine {
        return { file: this.file, line: this.template.firstLine + lineOfTemplate - 1 };
    }

    /** Adds the HTML up to `end` to the open block and continues behind the Twig tag that follows it. */
    private addMarkup(end: number, continueAt = end): void {
        this.markup.take(end, continueAt).forEach((markup) => this.add(markup));
    }

    /** `within` counts the conditional elements that opened after the block around the current node. */
    private place(): Placement {
        const inBlock = this.branches.slice(this.open.at(-1)?.depth ?? 0);

        return {
            within: inBlock.reduce(inBothStates, BOTH_STATES),
            states: this.branches.reduce(inBothStates, BOTH_STATES),
        };
    }

    private openBlock(name: string, at: SourceLine, markers: Marker[]): void {
        const block: BlockNode = { kind: 'block', name, origin: null, content: [], at, markers, ...this.place() };

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
    return new BlockReader(twigToHtml(templateOf(source, file), file), flags).read();
}

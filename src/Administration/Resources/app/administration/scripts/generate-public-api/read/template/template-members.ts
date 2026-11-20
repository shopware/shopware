/**
 * @sw-package framework
 *
 * Reads what the template of a component offers besides its blocks: the slots it renders, the template refs
 * plugin code reaches through `this.$refs`, and the CSS classes plugin styles select. All of it is read from the
 * template the component renders after the merge, so an extend child lists what its parent renders too.
 */
import { NodeTypes } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import ts from 'typescript';
import { byCodePoint } from '../../code-point-order';
import { BOTH_STATES, inBothStates, mergeStates } from '../../model/flag-states';
import type { FlagContext } from '../../model/flag-states';
import type { ComponentMembers, Member, TemplateKind, TemplatePlace } from '../../model/members';
import { memberName } from '../script-nodes';
import type { BlockNode } from './block-tree';
import { BranchStack, walkBranches } from './flag-branches';
import { blockBoundary, lineAt } from './rendered-template';
import type { RenderedTemplate } from './rendered-template';
import { contentOf, directive, staticAttribute } from './template-nodes';

type TemplateMember = Member<TemplateKind>;

/** What the reader knows of a member from its element alone, before it adds the place of the element. */
type Found = TemplateMember extends infer Each
    ? Each extends TemplateMember
        ? Omit<Each, keyof TemplatePlace | 'at' | 'markers' | 'isPrivate' | 'states'>
        : never
    : never;

const SLOT_TAG = 'slot';

/** The `v-bind` of the element for the attribute `name`, as `:ref="..."` is for `ref`. */
function binding(element: ElementNode, name: string): DirectiveNode | undefined {
    return element.props.find(
        (prop): prop is DirectiveNode =>
            prop.type === NodeTypes.DIRECTIVE && prop.name === 'bind' && contentOf(prop.arg) === name,
    );
}

function onOneLine(expression: string | undefined): string {
    return (expression ?? '').replace(/\s+/g, ' ').trim();
}

/** The class names a `:class` value names as literals: strings, the keys of objects, and both inside arrays. */
function literalClasses(value: ts.Expression): string[] {
    if (ts.isStringLiteralLike(value)) {
        return value.text.split(/\s+/);
    }

    if (ts.isArrayLiteralExpression(value)) {
        return value.elements.flatMap(literalClasses);
    }

    return ts.isObjectLiteralExpression(value)
        ? value.properties.flatMap((property) => memberName(property)?.split(/\s+/) ?? [])
        : [];
}

function boundClasses(expression: string | undefined): string[] {
    const [statement] = ts.createSourceFile('class.ts', `(${expression ?? ''});`, ts.ScriptTarget.Latest, true).statements;
    const value = statement && ts.isExpressionStatement(statement) ? statement.expression : undefined;

    return value !== undefined && ts.isParenthesizedExpression(value) ? literalClasses(value.expression) : [];
}

function classesOf(element: ElementNode): string[] {
    return [
        ...(staticAttribute(element, 'class')?.split(/\s+/) ?? []),
        ...boundClasses(contentOf(binding(element, 'class')?.exp)),
    ].filter((name) => name !== '');
}

/** The props a `<slot>` passes to its content: its attributes and bindings besides the name of the slot. */
function slotScope(slot: ElementNode): string[] {
    const names = slot.props.flatMap((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return [prop.name];
        }

        if (prop.name !== 'bind') {
            return [];
        }

        return [contentOf(prop.arg) ?? `...${onOneLine(contentOf(prop.exp))}`];
    });

    return names.filter((name) => name !== 'name').sort(byCodePoint);
}

/**
 * What makes two places of a member differ for a plugin. A class is the same wherever it stands, a ref points to
 * one kind of target, and a slot is rendered inside one block.
 */
function placeKey(member: TemplateMember): string {
    const details = {
        target: member.kind === 'ref' ? [member.target, member.list] : null,
        slot: member.kind === 'slot' ? [member.block, member.scope] : null,
    };

    return JSON.stringify([member.kind, member.name, details]);
}

class TemplateMemberReader {
    private readonly members: TemplateMember[] = [];

    private readonly notes = new Set<string>();

    /** The open blocks, each with the number of conditional elements around its start. */
    private readonly open: { block: BlockNode; depth: number }[] = [];

    private readonly branches = new BranchStack();

    private loops = 0;

    public constructor(
        private readonly template: RenderedTemplate,
        private readonly flags: FlagContext,
    ) {}

    public read(): ComponentMembers {
        this.visit(this.template.nodes);

        return {
            members: mergeStates(this.members, placeKey),
            notes: [...this.notes].sort(byCodePoint),
            sections: {},
        };
    }

    private visit(nodes: TemplateChildNode[]): void {
        walkBranches(nodes, this.flags, {
            comment: (content) => this.visitComment(content),
            element: (element, branch) => this.branches.enter(branch, () => this.visitElement(element)),
        });
    }

    private visitComment(content: string): void {
        const boundary = blockBoundary(this.template, content);

        if (boundary === 'end') {
            this.open.pop();
        } else if (boundary !== null) {
            this.open.push({ block: boundary, depth: this.branches.depth });
        }
    }

    private visitElement(element: ElementNode): void {
        const isLoop = directive(element, ['for']) !== undefined;

        this.loops += isLoop ? 1 : 0;
        this.readRef(element);
        this.readSlot(element);
        classesOf(element).forEach((name) => this.add(element, { kind: 'class', name }));
        this.visit(element.children);
        this.loops -= isLoop ? 1 : 0;
    }

    private readRef(element: ElementNode): void {
        const name = staticAttribute(element, 'ref');
        const bound = binding(element, 'ref');

        if (name !== undefined) {
            this.add(element, { kind: 'ref', name, target: element.tag, list: this.loops > 0 });
        } else if (bound !== undefined) {
            this.notes.add(`dynamic-ref ${onOneLine(contentOf(bound.exp))}`);
        }
    }

    private readSlot(element: ElementNode): void {
        const bound = binding(element, 'name');

        if (element.tag !== SLOT_TAG) {
            return;
        }

        if (bound !== undefined) {
            this.notes.add(`dynamic-slot ${onOneLine(contentOf(bound.exp))}`);

            return;
        }

        this.add(element, { kind: 'slot', name: staticAttribute(element, 'name') ?? 'default', scope: slotScope(element) });
    }

    /** A member renders where its block does, unless a conditional element between the two leaves a state out. */
    private add(element: ElementNode, found: Found): void {
        const place = this.open.at(-1);

        this.members.push({
            ...found,
            block: place?.block.name ?? null,
            at: lineAt(this.template, element.loc.start.offset),
            markers: [],
            isPrivate: false,
            states: inBothStates(place?.block.states ?? BOTH_STATES, this.branches.within(place?.depth)),
        });
    }
}

/**
 * @private
 *
 * The slots, template refs and CSS classes of the rendered `template`, each once per place that differs in what
 * a plugin sees of it.
 */
export function readTemplateMembers(template: RenderedTemplate, flags: FlagContext): ComponentMembers {
    return new TemplateMemberReader(template, flags).read();
}

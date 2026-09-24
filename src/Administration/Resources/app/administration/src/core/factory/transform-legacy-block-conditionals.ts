/**
 * @sw-package framework
 *
 * Under TwigJS, blocks vanish from the merged template, so a `v-else` in one block continues a `v-if` in the
 * block before it or in the block's parent content. Native blocks and Twig shims render those cases in separate
 * layers, where Vue cannot link them. This rewrites every case of such a chain into a plain `v-if` that calls a
 * `$swLegacyBlock*` helper (see `legacy-condition-context.ts`), which evaluates the chain across layers.
 *
 * A case is addressed by its chain key (`<block of the starting case>:<chain index in that block>`), its
 * render-order segment and its index within that segment.
 */
import { NodeTypes, parse, type DirectiveNode, type ElementNode, type TemplateChildNode } from '@vue/compiler-dom';
import type { LegacyConditionRenderOrderSegment } from 'src/app/component/structure/sw-block-override/shim/legacy-condition-context';

/**
 * The cases a shim entry renders for one chain.
 *
 * @private
 */
export type LegacyConditionCaseReservation = {
    chainKey: string;
    caseCount: number;
    caseStartIndex: number;
    startsChain?: boolean;
};

/**
 * @private
 */
export type LegacyTwigBlockSequenceEntry = {
    blockName: string;
    innerTemplate: string;
};

/**
 * @private
 */
export type LegacyTwigBlockSequenceTransformEntry = LegacyTwigBlockSequenceEntry & {
    legacyConditionCases: LegacyConditionCaseReservation[];
};

/**
 * @private
 */
export type BlockEntry = {
    componentName: string;
    innerTemplate: string;
    legacyConditionCases: LegacyConditionCaseReservation[];
};

type Chain = {
    blockName: string;
    index: number;
    cases: DirectiveNode[];
    starting: boolean;
    ending: boolean;
    first: boolean;
    last: boolean;
    followed: boolean;
    key: string;
    caseStart: number;
};

type BlockChains = {
    segment: LegacyConditionRenderOrderSegment;
    chains: Chain[];
};

type Edit = { start: number; end: number; text: string };

const HELPERS: Record<string, string> = {
    if: '$swLegacyBlockIf',
    'else-if': '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
};
const CONDITIONAL_REG_EXP = /v-(?:if|else-if|else)\b/;

// Per component: block-local chain key -> key of the chain it continues in the host template. Twig shims of the
// continuing block are indexed without seeing the host template, so they read their chain key from here.
const continuationAliases = new Map<string, Map<string, string>>();
const aliasRevisions = new Map<string, number>();
let aliasRevision = 0;

function storeContinuationAlias(componentName: string, localKey: string, chainKey: string): void {
    const aliases = continuationAliases.get(componentName) ?? new Map<string, string>();

    if (aliases.get(localKey) === chainKey) {
        return;
    }

    aliases.set(localKey, chainKey);
    continuationAliases.set(componentName, aliases);
    aliasRevisions.set(componentName, (aliasRevisions.get(componentName) ?? 0) + 1);
    aliasRevision += 1;
}

function parseChildren(template: string): TemplateChildNode[] {
    return parse(template, { onError: () => {}, onWarn: () => {} }).children;
}

function elementChildren(nodes: TemplateChildNode[]): ElementNode[] {
    return nodes.filter((node): node is ElementNode => node.type === NodeTypes.ELEMENT);
}

function staticAttribute(element: ElementNode, name: string): string | undefined {
    const attribute = element.props.find((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === name);

    return attribute?.type === NodeTypes.ATTRIBUTE ? (attribute.value?.content ?? '') : undefined;
}

function conditionalDirective(element: ElementNode): DirectiveNode | undefined {
    return element.props.find((prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && prop.name in HELPERS);
}

/**
 * The `v-if` / `v-else-if` / `v-else` chains among the top-level elements of one block.
 */
function collectChains(blockName: string, elements: ElementNode[]): Chain[] {
    const chains: Chain[] = [];
    let open: Chain | undefined;

    elements.forEach((element, position) => {
        const directive = conditionalDirective(element);

        if (!directive || directive.name === 'if') {
            open = undefined;
        }

        if (!directive) {
            return;
        }

        if (!open) {
            open = {
                blockName,
                index: chains.length,
                cases: [],
                starting: directive.name === 'if',
                ending: false,
                first: position === 0,
                last: false,
                followed: false,
                key: `${blockName}:${chains.length}`,
                caseStart: 0,
            };
            chains.push(open);
        }

        open.cases.push(directive);
        open.last = position === elements.length - 1;

        if (directive.name === 'else') {
            open.ending = true;
            open = undefined;
        }
    });

    return chains;
}

/**
 * Walks the blocks in render order: a chain that does not start with `v-if` continues the last started chain
 * and takes its key. Case indexes count per chain and segment, starting after `offsets[chainKey]`.
 */
function linkChains(blocks: BlockChains[], offsets: Record<string, number>, componentName?: string): void {
    let lead: Chain | undefined;
    let last: Chain | undefined;
    const nextCase = new Map<string, number>();

    blocks.forEach(({ segment, chains }) => {
        chains.forEach((chain) => {
            if (chain.starting) {
                lead = chain;
                last = chain;
            } else if (lead && last) {
                last.followed = true;

                if (componentName && segment !== 'shimExtension' && chain.key !== lead.key) {
                    storeContinuationAlias(componentName, chain.key, lead.key);
                }

                chain.key = lead.key;
            }

            const segmentKey = `${chain.key}:${segment}`;
            chain.caseStart = nextCase.get(segmentKey) ?? offsets[chain.key] ?? 0;
            nextCase.set(segmentKey, chain.caseStart + chain.cases.length);

            if (!chain.starting) {
                last = chain.ending ? undefined : chain;
                lead = chain.ending ? undefined : lead;
            }
        });
    });
}

/**
 * Chains at the edge of a block can be continued by a layer this template does not know about.
 */
function needsRewrite(chain: Chain): boolean {
    return !chain.starting || chain.followed || chain.first || chain.last;
}

function escapeAttribute(value: string): string {
    return value.replace(/"/g, '&quot;');
}

function chainEdits(chain: Chain, segment: LegacyConditionRenderOrderSegment, chainKey: string): Edit[] {
    const key = escapeAttribute(chainKey.replace(/\\/g, '\\\\').replace(/'/g, "\\'"));

    return chain.cases.map((directive, position) => {
        const expression = directive.name === 'else' ? '' : `${escapeAttribute(directive.exp?.loc.source ?? '')}, `;
        const options = [
            `{ segmentCaseIndex: ${chain.caseStart + position},`,
            `isStartingCondition: ${chain.starting && position === 0},`,
            `renderOrderSegment: '${segment}' }`,
        ].join(' ');

        return {
            start: directive.loc.start.offset,
            end: directive.loc.end.offset,
            text: `v-if="${HELPERS[directive.name]}('${key}', ${expression}${options})"`,
        };
    });
}

function applyEdits(template: string, edits: Edit[]): string {
    return [...edits]
        .sort((a, b) => b.start - a.start)
        .reduce((result, { start, end, text }) => result.slice(0, start) + text + result.slice(end), template);
}

/**
 * Rewrites the chains of the native `<sw-block>`s in a Twig-rendered component template.
 *
 * @private
 */
export default function transformNativeLegacyBlockConditionals(template: string, componentName?: string): string {
    if (!template.includes('<sw-block') || !CONDITIONAL_REG_EXP.test(template)) {
        return template;
    }

    const blocks: BlockChains[] = [];
    const visit = (nodes: TemplateChildNode[]) => {
        elementChildren(nodes).forEach((element) => {
            const extendsName = staticAttribute(element, 'extends');
            const blockName = staticAttribute(element, 'name') ?? extendsName;

            if (element.tag === 'sw-block' && blockName !== undefined) {
                blocks.push({
                    segment: extendsName !== undefined ? 'nativeExtension' : 'defaultSlot',
                    chains: collectChains(blockName, elementChildren(element.children)),
                });
            }

            visit(element.children);
        });
    };

    visit(parseChildren(template));
    linkChains(blocks, {}, componentName);

    return applyEdits(
        template,
        blocks.flatMap(({ segment, chains }) =>
            chains.filter(needsRewrite).flatMap((chain) => chainEdits(chain, segment, chain.key)),
        ),
    );
}

/**
 * Rewrites the chains of the top-level blocks of Twig override templates for one component, in registration
 * order. `offsets` holds the next free shim case index per chain key from earlier components.
 *
 * @private
 */
export function transformLegacyTwigBlockSequenceConditionals(
    entries: LegacyTwigBlockSequenceEntry[],
    componentName: string,
    offsets: Record<string, number> = {},
): LegacyTwigBlockSequenceTransformEntry[] {
    const parsed = entries.map((entry) => ({
        entry,
        chains: collectChains(entry.blockName, elementChildren(parseChildren(entry.innerTemplate))),
    }));
    const aliases = continuationAliases.get(componentName);
    const keyOf = (chain: Chain) => aliases?.get(`${chain.blockName}:${chain.index}`) ?? chain.key;

    linkChains(
        parsed.map(({ chains }) => ({ segment: 'shimExtension', chains })),
        offsets,
        componentName,
    );

    return parsed.map(({ entry, chains }) => {
        const rewritten = chains.every((chain) => chain.starting && !chain.followed) ? [] : chains.filter(needsRewrite);

        return {
            blockName: entry.blockName,
            innerTemplate: applyEdits(
                entry.innerTemplate,
                rewritten.flatMap((chain) => chainEdits(chain, 'shimExtension', keyOf(chain))),
            ),
            legacyConditionCases: rewritten.map((chain) => ({
                chainKey: keyOf(chain),
                caseCount: chain.cases.length,
                caseStartIndex: chain.caseStart,
                ...(chain.starting ? { startsChain: true } : {}),
            })),
        };
    });
}

/**
 * Changes whenever a native template transform records a new continuation alias, globally or for one component.
 *
 * @private
 */
export function getContinuationAliasRevision(componentName?: string): number {
    return componentName === undefined ? aliasRevision : (aliasRevisions.get(componentName) ?? 0);
}

/**
 * @private
 */
export function resetContinuationAliases(): void {
    continuationAliases.clear();
    aliasRevisions.clear();
    aliasRevision += 1;
}

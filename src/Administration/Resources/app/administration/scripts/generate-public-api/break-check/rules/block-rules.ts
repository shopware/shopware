/**
 * @sw-package framework
 *
 * The rules of the break check for blocks. Each one says in its texts what a plugin override of the block
 * notices of the change.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { blockLabel, crossingId } from '../../model/blocks';
import type { Crossing } from '../../model/blocks';
import { sourceLabel } from '../facts/block-facts';
import type { BlockFacts } from '../facts/block-facts';
import type { UnitFacts } from '../facts/unit-facts';
import { isMajorOnly } from '../finding';
import { blockOverride } from '../plugin-use';
import { rule } from './rule';
import type { Rule } from './rule';
import { bcChangeTag, inMajorState, removalTag, renameTag, twigComment } from './texts';

/** The block as the element of a change, or something `below` it such as `/scope:item`. A plugin overrides it. */
function blockElement({ name, at }: BlockFacts, below = '') {
    return { element: `block:${name}${below}`, at, block: name, example: () => blockOverride(name) };
}

/** A block that both commits have. */
interface KeptBlock {
    was: BlockFacts;
    now: BlockFacts;
}

/** The block of a change that both commits have, with its line at each of them. */
function keptBlock({ was, now }: KeptBlock, below = '') {
    return { ...blockElement(now, below), atBase: was.at };
}

/** A variable of the scope of the block, which an override of the block uses. */
function scopeElement(block: KeptBlock, variable: string) {
    return {
        ...keptBlock(block, `/scope:${variable}`),
        variable,
        example: () => blockOverride(block.now.name, `<my-plugin-content :value="${variable}" />`),
    };
}

/** The markers in the comments before the block of a change, which can announce it. */
function blockMarkers({ block }: { block: string }, facts: UnitFacts): Marker[] {
    return facts.blocks.get(block)?.markers ?? [];
}

function listed(blocks: (string | null)[]): string {
    return blocks.map(blockLabel).join(' and ');
}

function quoted(values: string[]): string {
    return `"${values.join('" or "')}"`;
}

function inBoth(base: UnitFacts, head: UnitFacts): KeptBlock[] {
    return [...base.blocks.values()].flatMap((was) => {
        const now = head.blocks.get(was.name);

        return now === undefined ? [] : [{ was, now }];
    });
}

function added(base: UnitFacts, head: UnitFacts): BlockFacts[] {
    return [...head.blocks.values()].filter(({ name }) => !base.blocks.has(name));
}

/** A new block under a parent of the removed one is probably the same block under a new name. */
function possibleRename(removed: BlockFacts, base: UnitFacts, head: UnitFacts): string | undefined {
    const candidates = added(base, head).filter(({ parents }) => parents.some((parent) => removed.parents.includes(parent)));

    return candidates.length === 1 ? candidates[0].name : undefined;
}

/** The blocks around the removed block that the head still has. */
function remainingParents({ parents }: BlockFacts, head: UnitFacts): BlockFacts[] {
    return parents.flatMap((parent) => {
        const block = parent === null ? undefined : head.blocks.get(parent);

        return block === undefined ? [] : [block];
    });
}

/** The block of an extend child that stopped keeping the parent content, which held the removed block. */
function droppedWith(removed: BlockFacts, base: UnitFacts, head: UnitFacts): BlockFacts | undefined {
    if (!removed.origins.includes('inherited')) {
        return undefined;
    }

    return remainingParents(removed, head).find(
        (parent) => parent.origins.includes('replaced') && !base.blocks.get(parent.name)?.origins.includes('replaced'),
    );
}

const blockMissing = rule({
    id: 'block_missing',
    group: 'Break',
    changes: (base, head) =>
        [...base.blocks.values()]
            .filter(({ name }) => !head.blocks.has(name))
            .map((removed) => {
                const dropped = droppedWith(removed, base, head);
                const [parent] = remainingParents(removed, head);

                return {
                    ...blockElement(removed),
                    // The block has no line at the head, so the finding points to the block that held it.
                    at: (dropped ?? parent)?.at,
                    atBase: removed.at,
                    isGone: true as const,
                    rename: possibleRename(removed, base, head),
                    droppedWith: dropped?.name,
                };
            }),
    texts: ({ block: name, rename, droppedWith: parent }, { flags, unit: component, states }) => {
        const majorOnly = isMajorOnly(states);
        const headline = `The block ${name} is gone${inMajorState(states)}`;
        const explanation = majorOnly
            ? `The block is gone in the major state, so plugin template overrides of ${name} render nothing with ${flags.majorFlag} on.`
            : `The block is gone, so plugin template overrides of ${name} render nothing.`;

        if (rename !== undefined) {
            const keep = majorOnly ? `Keep ${name} in the branch for ${flags.majorFlag} off` : `Keep ${name}`;
            const marker = twigComment(renameTag(rename, flags));

            return {
                headline,
                explanation,
                fix: `pattern "Rename". ${keep} and mark it ${marker}.`,
                marker,
                possibleRename: rename,
            };
        }

        const marker = twigComment(removalTag(flags));

        return {
            headline,
            explanation,
            fix:
                parent === undefined
                    ? `pattern "Removal". Keep the block, even empty, and mark it ${marker}.`
                    : `pattern "Removal". Keep {% parent %} in ${parent}, so ${component} still inherits ${name}, or keep the block and mark it ${marker}.`,
            marker,
        };
    },
    announcement: {
        rule: 'deprecated_removal',
        subject: 'The block',
        // A block goes away with a deprecated block around it.
        markers: ({ block }, facts) => facts.blocks.get(block)?.deprecations ?? [],
        matches: (_change, marker) => marker.tag === 'deprecated',
    },
});

const blockAdded = rule({
    id: 'block_added',
    group: 'Addition',
    changes: (base, head) => added(base, head).map((block) => blockElement(block)),
    texts: () => ({
        explanation: 'A new public block that plugins can rely on from now on.',
        fix: 'mark it @private if it is not meant for plugins.',
    }),
});

/** The blocks of `around` that both commits have and that `other` does not have around the block. */
function onlyAround(around: BlockFacts, other: BlockFacts, base: UnitFacts, head: UnitFacts): string[] {
    return [...around.ancestors].filter(
        (ancestor) => base.blocks.has(ancestor) && head.blocks.has(ancestor) && !other.ancestors.has(ancestor),
    );
}

function hasSameParents(was: BlockFacts, now: BlockFacts): boolean {
    return was.parents.length === now.parents.length && was.parents.every((parent) => now.parents.includes(parent));
}

/** A plugin can override a block, but not the template outside of every block. */
function overridable(parents: (string | null)[]): string[] {
    return parents.filter((parent): parent is string => parent !== null);
}

function movedOverrideEffects(from: (string | null)[], to: (string | null)[]): string {
    const left = overridable(from);
    const entered = overridable(to);

    if (left.length === 0) {
        return `plugin overrides of ${listed(entered)} drop it`;
    }

    const leftEffect = `plugin overrides of ${listed(left)} without {% parent %} now drop it`;

    return entered.length === 0 ? leftEffect : `${leftEffect}, and overrides of ${listed(entered)} drop it too`;
}

/**
 * A block moved when it left a block that still exists, or entered one that existed before: overrides of that
 * block now render without it or drop it. A block that only got a new block around it, or lost one that is
 * gone, sits where it sat for every existing override. A block that keeps its parent moved with that parent,
 * which is reported instead.
 */
const blockMovedParent = rule({
    id: 'block_moved_parent',
    group: 'Break',
    changes: (base, head) =>
        inBoth(base, head)
            .filter(({ was, now }) => !hasSameParents(was, now))
            .filter(({ was, now }) => onlyAround(was, now, base, head).length + onlyAround(now, was, base, head).length > 0)
            .map((block) => {
                const [from, to] = [block.was.parents, block.now.parents];
                // An override of the block itself still works, the one of a block it left or entered does not.
                const [overridden] = [...overridable(from), ...overridable(to)];

                return {
                    ...keptBlock(block),
                    from,
                    to,
                    example: () => (overridden === undefined ? undefined : blockOverride(overridden)),
                };
            }),
    texts: ({ block: name, from, to }, { flags }) => {
        const marker = twigComment(bcChangeTag('BlockMoved', flags, `parent:${listed(to)}`));

        return {
            headline: `The block ${name} moved to ${listed(to)}`,
            explanation: `The block moved from ${listed(from)} to ${listed(to)}, so ${movedOverrideEffects(from, to)}.`,
            fix:
                `pattern "Block move". Keep the block under ${listed(from)} while ${flags.majorFlag} is off, move it only ` +
                `with the flag on, and mark it ${marker}.`,
            marker,
        };
    },
    announcement: {
        rule: 'announced_change',
        subject: 'The block',
        markers: (change, facts) => blockMarkers(change, facts),
        matches: ({ to }, marker) =>
            marker.tag === 'bc-change' &&
            marker.kind === 'BlockMoved' &&
            to.map(blockLabel).includes(marker.parameters.parent),
    },
});

function lostIfContext(base: UnitFacts, head: UnitFacts): { was: BlockFacts; now: BlockFacts }[] {
    return inBoth(base, head).filter(({ was, now }) => was.afterIf && !now.afterIf);
}

/** The content of the block continued the chain, so an override of the block has to start the same way. */
const blockIfContextLost = rule({
    id: 'block_if_context_lost',
    group: 'Break',
    changes: (base, head) =>
        lostIfContext(base, head)
            .filter(({ was }) => was.continuesChain)
            .map((block) => ({
                ...keptBlock(block),
                example: () => blockOverride(block.now.name, '<my-plugin-content v-else />'),
            })),
    texts: ({ block: name }) => ({
        headline: `The block ${name} no longer follows a v-if`,
        explanation:
            'The block no longer follows an open v-if chain, so plugin overrides that start with v-else-if or v-else ' +
            'no longer compile.',
        fix: 'keep the v-if element directly before the block.',
    }),
});

/**
 * The block only stood behind a conditional element. Its own content never continued that chain, so an
 * override had no reason to. The history of the Administration has this far more often than a real chain.
 */
const blockIfNeighbourLost = rule({
    id: 'block_if_neighbour_lost',
    group: 'Info',
    changes: (base, head) =>
        lostIfContext(base, head)
            .filter(({ was }) => !was.continuesChain)
            .map((block) => keptBlock(block)),
    texts: () => ({
        explanation:
            'The block no longer stands behind an element with v-if. Its content did not continue that chain, so only ' +
            'a plugin override that starts with v-else-if or v-else on its own no longer compiles.',
        fix: 'mention it in the upgrade notes if plugins are likely to continue the chain there.',
    }),
});

/** The text of a condition cannot tell a refined condition from another one, so this is no break. */
const blockIfChainChanged = rule({
    id: 'block_if_chain_changed',
    group: 'Info',
    changes: (base, head) =>
        inBoth(base, head)
            .filter(({ was, now }) => was.afterIf && now.afterIf && was.chains.some((chain) => !now.chains.includes(chain)))
            .map((block) => ({ ...keptBlock(block), from: block.was.chains, to: block.now.chains })),
    texts: ({ from, to }) => ({
        explanation:
            `The v-if chain before the block changed from ${quoted(from)} to ${quoted(to)}, so plugin overrides ` +
            'that start with v-else-if or v-else may now render under another condition.',
        fix: 'mention it in the upgrade notes if the block now belongs to another condition.',
    }),
});

const blockScopeMissing = rule({
    id: 'block_scope_missing',
    group: 'Break',
    changes: (base, head) =>
        inBoth(base, head).flatMap((block) =>
            [...block.was.scope]
                .filter(([variable]) => !block.now.scope.has(variable))
                .map(([variable, [{ kind }]]) => ({ ...scopeElement(block, variable), kind })),
        ),
    texts: ({ block: name, variable, kind }, { flags }) => {
        const marker = twigComment(bcChangeTag('ScopeVariableRemoval', flags, `variable:${variable}`));

        return {
            headline: `The template variable ${variable} is no longer available`,
            explanation:
                `The template variable ${variable} from a ${kind} is no longer available in block ${name}, so plugin ` +
                `overrides that use ${variable} fail at render time.`,
            fix:
                `pattern "Scope variable removal". Keep ${variable} available in the block while ${flags.majorFlag} is off, ` +
                `and mark the block ${marker}.`,
            marker,
        };
    },
    announcement: {
        rule: 'announced_change',
        subject: 'The block',
        markers: (change, facts) => blockMarkers(change, facts),
        matches: ({ variable }, marker) =>
            marker.tag === 'bc-change' && marker.kind === 'ScopeVariableRemoval' && marker.parameters.variable === variable,
    },
});

/** The name of a source says nothing about the shape of its values, so this is no break. */
const blockScopeSourceChanged = rule({
    id: 'block_scope_source_changed',
    group: 'Info',
    changes: (base, head) =>
        inBoth(base, head).flatMap((block) =>
            [...block.was.scope].flatMap(([variable, sources]) => {
                const from = sources.map(sourceLabel);
                const to = (block.now.scope.get(variable) ?? []).map(sourceLabel);

                return to.length === 0 || from.every((source) => to.includes(source))
                    ? []
                    : [{ ...scopeElement(block, variable), from, to }];
            }),
        ),
    texts: ({ block: name, variable, from, to }) => ({
        explanation:
            `The template variable ${variable} in block ${name} now comes from ${quoted(to)} instead of ` +
            `${quoted(from)}, so plugin overrides that use ${variable} may get another value.`,
        fix: 'mention it in the upgrade notes if the value has another shape now.',
    }),
});

function crossingsOnlyIn(commit: UnitFacts, other: UnitFacts): Crossing[] {
    return [...commit.crossings.values()].filter((crossing) => !other.crossings.has(crossingId(crossing)));
}

/** A crossing breaks the override of one of its two blocks. */
function crossingElement(crossing: Crossing) {
    const [overridden] = overridable([crossing.opens, crossing.closes]);

    return {
        element: `crossing:${crossingId(crossing)}`,
        opens: blockLabel(crossing.opens),
        closes: blockLabel(crossing.closes),
        example: () => (overridden === undefined ? undefined : blockOverride(overridden)),
    };
}

const elementCrossesBlock = rule({
    id: 'element_crosses_block',
    group: 'Break',
    changes: (base, head) =>
        crossingsOnlyIn(head, base).flatMap((crossing) =>
            crossing.kind === 'element' ? [{ ...crossingElement(crossing), at: crossing.at, tag: crossing.tag }] : [],
        ),
    texts: ({ tag, opens, closes }) => ({
        headline: `A <${tag}> element opens in block ${opens} and closes in block ${closes}`,
        explanation:
            `The <${tag}> element opens in block ${opens} and closes in block ${closes}, so plugin overrides of ` +
            'either block without {% parent %} break the markup.',
        fix: 'open and close the element inside the same block.',
    }),
});

const ifChainCrossesBlock = rule({
    id: 'if_chain_crosses_block',
    group: 'Break',
    changes: (base, head) =>
        crossingsOnlyIn(head, base)
            .filter(({ kind }) => kind === 'chain')
            .map((crossing) => ({ ...crossingElement(crossing), at: crossing.at })),
    texts: ({ opens, closes }) => ({
        headline: `A v-if chain starts in block ${opens} and continues in block ${closes}`,
        explanation:
            `The v-if chain of block ${opens} continues in block ${closes}, so overriding ${opens} without ` +
            `{% parent %} leaves the v-else-if or v-else in ${closes} without its v-if.`,
        fix: 'keep the whole chain inside one block.',
    }),
});

// The line of a crossing that is gone is one of the base commit, which a finding cannot point to.
const crossingResolved = rule({
    id: 'crossing_resolved',
    group: 'Info',
    changes: (base, head) => crossingsOnlyIn(base, head).map(crossingElement),
    texts: ({ opens, closes }) => ({
        explanation: `The crossing between block ${opens} and block ${closes} is gone.`,
    }),
});

/** @private */
export const BLOCK_RULES: readonly Rule[] = [
    blockMissing,
    blockAdded,
    blockMovedParent,
    blockIfContextLost,
    blockIfNeighbourLost,
    blockIfChainChanged,
    blockScopeMissing,
    blockScopeSourceChanged,
    elementCrossesBlock,
    ifChainCrossesBlock,
    crossingResolved,
];

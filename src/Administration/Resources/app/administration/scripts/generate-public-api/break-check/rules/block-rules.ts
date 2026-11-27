/**
 * @sw-package framework
 *
 * The rules of the break check for blocks. Each one says in its texts what a plugin override of the block
 * notices of the change.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { blockLabel, crossingId } from '../../model/blocks';
import type { Crossing } from '../../model/blocks';
import type { FlagContext } from '../../model/flag-states';
import { sourceLabel } from '../facts/block-facts';
import type { BlockFacts } from '../facts/block-facts';
import type { UnitFacts } from '../facts/unit-facts';
import { isMajorOnly } from '../finding';
import { rule } from './rule';
import type { Rule } from './rule';

/** The block as the element of a change, or something `below` it such as `/scope:item`. */
function blockElement({ name, at }: BlockFacts, below = '') {
    return { element: `block:${name}${below}`, at, block: name };
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

function inBoth(base: UnitFacts, head: UnitFacts): { was: BlockFacts; now: BlockFacts }[] {
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

function deprecation(flags: FlagContext, text: string): string {
    return `{# @deprecated tag:${flags.majorVersion} - ${text} #}`;
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
                    rename: possibleRename(removed, base, head),
                    droppedWith: dropped?.name,
                };
            }),
    texts: ({ block: name, rename, droppedWith: parent }, { flags, unit: component, states }) => {
        const majorOnly = isMajorOnly(states);
        const explanation = majorOnly
            ? `The block is gone in the major state, so plugin template overrides of ${name} render nothing with ${flags.majorFlag} on.`
            : `The block is gone, so plugin template overrides of ${name} render nothing.`;

        if (rename !== undefined) {
            const keep = majorOnly ? `Keep ${name} in the branch for ${flags.majorFlag} off` : `Keep ${name}`;

            return {
                explanation,
                fix: `pattern "Rename". ${keep} and mark it ${deprecation(flags, `Use {@link ${rename}} instead.`)}.`,
                possibleRename: rename,
            };
        }

        const mark = `mark it ${deprecation(flags, 'Will be removed.')}`;

        return {
            explanation,
            fix:
                parent === undefined
                    ? `pattern "Removal". Keep the block, even empty, and ${mark}.`
                    : `pattern "Removal". Keep {% parent %} in ${parent}, so ${component} still inherits ${name}, or keep the block and ${mark}.`,
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
            .map(({ was, now }) => ({ ...blockElement(now), from: was.parents, to: now.parents })),
    texts: ({ from, to }, { flags }) => ({
        explanation: `The block moved from ${listed(from)} to ${listed(to)}, so ${movedOverrideEffects(from, to)}.`,
        fix:
            `pattern "Block move". Keep the block under ${listed(from)} while ${flags.majorFlag} is off, move it only ` +
            `with the flag on, and mark it {# @bc-change BlockMoved version:${flags.majorVersion} parent:${listed(to)} #}.`,
    }),
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
            .map(({ now }) => blockElement(now)),
    texts: () => ({
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
            .map(({ now }) => blockElement(now)),
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
            .map(({ was, now }) => ({ ...blockElement(now), from: was.chains, to: now.chains })),
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
        inBoth(base, head).flatMap(({ was, now }) =>
            [...was.scope]
                .filter(([variable]) => !now.scope.has(variable))
                .map(([variable, [{ kind }]]) => ({ ...blockElement(now, `/scope:${variable}`), variable, kind })),
        ),
    texts: ({ block: name, variable, kind }, { flags }) => ({
        explanation:
            `The template variable ${variable} from a ${kind} is no longer available in block ${name}, so plugin ` +
            `overrides that use ${variable} fail at render time.`,
        fix:
            `pattern "Scope variable removal". Keep ${variable} available in the block while ${flags.majorFlag} is off, ` +
            `and mark the block {# @bc-change ScopeVariableRemoval version:${flags.majorVersion} variable:${variable} #}.`,
    }),
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
        inBoth(base, head).flatMap(({ was, now }) =>
            [...was.scope].flatMap(([variable, sources]) => {
                const from = sources.map(sourceLabel);
                const to = (now.scope.get(variable) ?? []).map(sourceLabel);

                return to.length === 0 || from.every((source) => to.includes(source))
                    ? []
                    : [{ ...blockElement(now, `/scope:${variable}`), variable, from, to }];
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

function crossingElement(crossing: Crossing) {
    return {
        element: `crossing:${crossingId(crossing)}`,
        opens: blockLabel(crossing.opens),
        closes: blockLabel(crossing.closes),
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

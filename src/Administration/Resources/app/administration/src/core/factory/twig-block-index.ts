/**
 * @sw-package framework
 * @private
 *
 * Indexes the `{% block %}`s of Twig override templates. `use-block-context.ts` renders each one as a layer of
 * the native `<sw-block name>` of the same name. TwigJS only parses here; `template.factory.js` has already
 * configured it (output tokens filtered, `{% parent %}` registered) before this module is used.
 *
 * Consecutive templates of one component form a group whose blocks are transformed together, because a `v-if`
 * chain can continue from one block into the next. Groups are transformed lazily on first read after they
 * changed, so the continuation aliases of the host templates, which are resolved later, are known by then.
 */
import Twig from 'twig';
import reconstructInnerTemplate, { type TwigToken } from './reconstruct-twig-template';
import {
    getContinuationAliasRevision,
    resetContinuationAliases,
    transformLegacyTwigBlockSequenceConditionals,
    type BlockEntry,
    type LegacyTwigBlockSequenceEntry,
} from './transform-legacy-block-conditionals';

/**
 * @private
 */
export type { BlockEntry } from './transform-legacy-block-conditionals';

type ParsedTwigToken = {
    type: string;
    token?: {
        blockName?: string;
        output?: TwigToken[];
    };
};

/**
 * @private
 */
export type TwigBlockRecord = LegacyTwigBlockSequenceEntry & {
    componentName: string;
    /** The override index of `Component.override()`. */
    priority: number;
    /** Blocks from `Component.extend()` only apply to the extending component and its children. */
    scoped: boolean;
    sequence: number;
    entry: BlockEntry;
};

type Group = { componentName: string; blocks: TwigBlockRecord[]; aliasRevision: number };

const groups: Group[] = [];
const recordsByBlock = new Map<string, TwigBlockRecord[]>();
let nextSequence = 0;
let dirtyFrom = Infinity;
let seenAliasRevision = getContinuationAliasRevision();

function ensureIndex(): void {
    if (seenAliasRevision !== getContinuationAliasRevision()) {
        seenAliasRevision = getContinuationAliasRevision();

        const stale = groups.findIndex(
            ({ componentName, aliasRevision }) => aliasRevision !== getContinuationAliasRevision(componentName),
        );
        dirtyFrom = stale === -1 ? dirtyFrom : Math.min(dirtyFrom, stale);
    }

    if (dirtyFrom >= groups.length) {
        return;
    }

    const offsets: Record<string, number> = {};

    groups.forEach((group, groupIndex) => {
        if (groupIndex >= dirtyFrom) {
            group.aliasRevision = getContinuationAliasRevision(group.componentName);
            transformLegacyTwigBlockSequenceConditionals(group.blocks, group.componentName, offsets).forEach(
                ({ innerTemplate, legacyConditionCases }, blockIndex) => {
                    Object.assign(group.blocks[blockIndex].entry, { innerTemplate, legacyConditionCases });
                },
            );
        }

        group.blocks.forEach(({ entry }) => {
            entry.legacyConditionCases.forEach(({ chainKey, caseStartIndex, caseCount }) => {
                offsets[chainKey] = Math.max(offsets[chainKey] ?? 0, caseStartIndex + caseCount);
            });
        });
    });

    dirtyFrom = Infinity;
}

function parseTwigBlocks(componentName: string, rawTemplate: string): LegacyTwigBlockSequenceEntry[] | null {
    try {
        const parsed = Twig.twig({ data: rawTemplate, rethrow: true });

        return (parsed.tokens as ParsedTwigToken[])
            .filter((token) => token.type === 'logic' && typeof token.token?.blockName === 'string')
            .map(({ token }) => ({
                blockName: token!.blockName!,
                innerTemplate: reconstructInnerTemplate(token!.output ?? []),
            }));
    } catch (error) {
        console.warn(`[sw-block] Failed to parse Twig template for "${componentName}":`, error);

        return null;
    }
}

/**
 * Indexes every top-level `{% block %}` of a Twig template of `componentName`. Templates from
 * `Component.override()` apply wherever the block is rendered; templates from `Component.extend()` are
 * `scoped` to the extending component and its children.
 *
 * @private
 */
export function indexTwigBlocksFromTemplate(
    componentName: string,
    rawTemplate: string,
    { priority = 0, scoped = false }: { priority?: number; scoped?: boolean } = {},
): void {
    const blocks = parseTwigBlocks(componentName, rawTemplate);

    if (!blocks) {
        return;
    }

    let group = groups[groups.length - 1];

    if (group?.componentName !== componentName) {
        group = { componentName, blocks: [], aliasRevision: -1 };
        groups.push(group);
    }

    dirtyFrom = Math.min(dirtyFrom, groups.length - 1);

    blocks.forEach(({ blockName, innerTemplate }) => {
        const record: TwigBlockRecord = {
            blockName,
            innerTemplate,
            componentName,
            priority,
            scoped,
            sequence: nextSequence++,
            entry: { componentName, innerTemplate, legacyConditionCases: [] },
        };

        group.blocks.push(record);
        recordsByBlock.set(blockName, [...(recordsByBlock.get(blockName) ?? []), record]);
    });
}

/**
 * @private
 */
export function getTwigBlockRecords(blockName: string): TwigBlockRecord[] {
    const records = recordsByBlock.get(blockName) ?? [];

    if (records.length > 0) {
        ensureIndex();
    }

    return records;
}

/**
 * @private
 */
export function getBlockEntries(blockName: string): BlockEntry[] {
    return getTwigBlockRecords(blockName).map(({ entry }) => entry);
}

/**
 * @private
 */
export function hasBlockEntries(blockName: string): boolean {
    return recordsByBlock.has(blockName);
}

/**
 * @private
 */
export function resetBlockIndex(): void {
    groups.length = 0;
    recordsByBlock.clear();
    dirtyFrom = Infinity;
    resetContinuationAliases();
    seenAliasRevision = getContinuationAliasRevision();
}

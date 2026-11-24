/**
 * @sw-package framework
 *
 * What the break check compares about the blocks of a component in one flag state. A block that the template
 * declares in several places has one entry, which holds what an override can rely on in every place.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { byCodePoint } from '../../helpers/code-point-order';
import { crossingId } from '../../model/blocks';
import type { BlockOrigin, Crossing, ScopeVariable, TemplateBlock } from '../../model/blocks';
import type { FlagState } from '../../model/flag-states';
import type { ComponentUnit } from '../../model/model';
import type { SourceLine } from '../../model/source-line';

/**
 * @private
 *
 * - `parents`: the block directly around each of its places, `null` for the template itself.
 * - `ancestors`: every block around one of its places.
 * - `afterIf`: every place follows an open `v-if` chain. `chains` holds the conditions of those chains, and
 *   `continuesChain` says that the content of the block continues the chain in one of its places.
 * - `scope`: the variables every place can use, each with the sources it has in those places.
 * - `deprecations`: the `@deprecated` marker that covers every place of the block, its own or that of a block
 *   around the place, because a block goes away with everything inside it. Empty when a place has none.
 */
export interface BlockFacts {
    name: string;
    parents: (string | null)[];
    ancestors: ReadonlySet<string>;
    origins: (BlockOrigin | null)[];
    afterIf: boolean;
    continuesChain: boolean;
    chains: string[];
    scope: ReadonlyMap<string, ScopeVariable[]>;
    markers: Marker[];
    deprecations: Marker[];
    at: SourceLine;
}

/** @private */
export interface BlockStructureFacts {
    blocks: ReadonlyMap<string, BlockFacts>;
    crossings: ReadonlyMap<string, Crossing>;
}

/**
 * @private
 *
 * Where a variable comes from, as a finding words it: `v-for products` or `slot sw-data-grid#actions`.
 */
export function sourceLabel({ kind, source }: ScopeVariable): string {
    return `${kind} ${source}`;
}

function unique<Value>(values: Value[]): Value[] {
    return [...new Set(values)];
}

function uniqueSorted(values: string[]): string[] {
    return unique(values).sort(byCodePoint);
}

function sharedScope(places: TemplateBlock[]): Map<string, ScopeVariable[]> {
    const [first, ...others] = places.map(({ context }) => context.scope);
    const everywhere = first.filter(({ name }) => others.every((scope) => scope.some((variable) => variable.name === name)));
    const sourcesOf = (name: string): ScopeVariable[] => {
        const bySource = new Map(
            places
                .flatMap(({ context }) => context.scope.filter((variable) => variable.name === name))
                .map((variable) => [sourceLabel(variable), variable]),
        );

        return [...bySource].sort(([a], [b]) => byCodePoint(a, b)).map(([, variable]) => variable);
    };

    return new Map(everywhere.map(({ name }) => [name, sourcesOf(name)]));
}

function byVersion(a: Marker, b: Marker): number {
    const [partsA, partsB] = [a, b].map(({ version }) => version.slice(1).split('.').map(Number));
    const differs = partsA.findIndex((part, index) => part !== partsB[index]);

    return differs === -1 ? 0 : partsA[differs] - partsB[differs];
}

/**
 * @private
 *
 * The deprecation that covers an element in every place, given the deprecations of each place. A place counts
 * from its earliest deprecation on, and the element once every place does, so from the latest of those.
 */
export function coveringDeprecation(places: Marker[][]): Marker[] {
    const earliest = places.flatMap((markers) => [...markers].sort(byVersion).slice(0, 1));

    return earliest.length > 0 && earliest.length === places.length ? earliest.sort(byVersion).slice(-1) : [];
}

function ancestorsOf(block: string, parents: ReadonlyMap<string, (string | null)[]>, seen: ReadonlySet<string>): string[] {
    return (parents.get(block) ?? [])
        .filter((parent): parent is string => parent !== null && !seen.has(parent))
        .flatMap((parent) => [
            parent,
            ...ancestorsOf(
                parent,
                parents,
                new Set([
                    ...seen,
                    parent,
                ]),
            ),
        ]);
}

/**
 * @private
 *
 * The blocks and crossings of the component that exist in `state`.
 */
export function blockStructureFactsOf(
    unit: Pick<ComponentUnit, 'blocks' | 'crossings'>,
    state: FlagState,
): BlockStructureFacts {
    const places = new Map<string, TemplateBlock[]>();

    unit.blocks
        .filter(({ states }) => states[state])
        .forEach((block) => places.set(block.name, [...(places.get(block.name) ?? []), block]));

    const parents = new Map([...places].map(([name, placed]) => [name, unique(placed.map(({ parent }) => parent))]));
    const deprecationsOf = (name: string, seen: ReadonlySet<string>): Marker[] =>
        coveringDeprecation(
            (places.get(name) ?? []).map(({ markers, parent }) => [
                ...markers.filter(({ tag }) => tag === 'deprecated'),
                ...(parent === null || seen.has(parent) ? [] : deprecationsOf(parent, new Set([...seen, parent]))),
            ]),
        );
    const blocks = [...places].map(([name, placed]): [string, BlockFacts] => [
        name,
        {
            name,
            parents: parents.get(name) ?? [],
            ancestors: new Set(ancestorsOf(name, parents, new Set([name]))),
            origins: unique(placed.map(({ origin }) => origin)),
            afterIf: placed.every(({ context }) => context.afterIf !== null),
            continuesChain: placed.some(({ context }) => context.continuesChain),
            chains: uniqueSorted(
                placed.flatMap(({ context }) => (context.afterIf === null ? [] : [context.afterIf.join(' ; ')])),
            ),
            scope: sharedScope(placed),
            markers: placed.flatMap(({ markers }) => markers),
            deprecations: deprecationsOf(name, new Set([name])),
            at: placed[0].at,
        },
    ]);

    return {
        blocks: new Map(blocks),
        crossings: new Map(
            unit.crossings.filter(({ states }) => states[state]).map((crossing) => [crossingId(crossing), crossing]),
        ),
    };
}

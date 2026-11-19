/**
 * @sw-package framework
 *
 * What the break check compares about the blocks of a component in one flag state. A block that the template
 * declares in several places has one entry, which holds what an override can rely on in every place.
 */
import type { Marker } from '../../eslint-rules/deprecation-rules/utils/api-markers';
import { blockLabel, crossingSubject } from './block-structure';
import type { Crossing } from './block-structure';
import type { BlockOrigin, ScopeVariable, SourceLine, TemplateBlock } from './block-tree';
import { byCodePoint } from './code-point-order';
import type { FlagState } from './flag-states';
import type { ComponentUnit } from './model';

/**
 * @private
 *
 * - `parents`: the block directly around each of its places, `null` for the template itself.
 * - `ancestors`: every block around one of its places.
 * - `afterIf`: every place follows an open `v-if` chain. `chains` holds the conditions of those chains, and
 *   `continuesChain` says that the content of the block continues the chain in one of its places.
 * - `scope`: the variables every place can use, each with the sources it has in those places.
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
    at: SourceLine;
}

/** @private */
export interface ComponentFacts {
    blocks: ReadonlyMap<string, BlockFacts>;
    crossings: ReadonlyMap<string, Crossing>;
}

/**
 * @private
 *
 * The part of the element id that names a crossing, `div@sw_card>sw_card_title`.
 */
export function crossingId(crossing: Crossing): string {
    return `${crossingSubject(crossing)}@${blockLabel(crossing.opens)}>${blockLabel(crossing.closes)}`;
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

        return uniqueSorted([...bySource.keys()]).flatMap((label) => bySource.get(label) ?? []);
    };

    return new Map(everywhere.map(({ name }) => [name, sourcesOf(name)]));
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
export function factsOf(unit: ComponentUnit, state: FlagState): ComponentFacts {
    const places = new Map<string, TemplateBlock[]>();

    unit.blocks
        .filter(({ states }) => states[state])
        .forEach((block) => places.set(block.name, [...(places.get(block.name) ?? []), block]));

    const parents = new Map([...places].map(([name, placed]) => [name, unique(placed.map(({ parent }) => parent))]));
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

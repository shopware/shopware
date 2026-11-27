/**
 * @sw-package framework
 *
 * The clusters of a report as a tree for layout "Split": first the kind of unit, then the folders of the sources,
 * then the units. A folder that only holds one other folder is joined with it, so the tree has no empty steps.
 */
import type { Cluster } from './clusters';
import { GROUP_ORDER, plural } from './report';
import type { FindingGroup } from './report';

/** @private */
export type TreeNode = 'kind' | 'folder' | 'unit';

/** @private */
export type TreeCount = Cluster['counts'][number];

/** @private */
export interface TreeUnit {
    cluster: Cluster;
    counts: TreeCount[];
}

/** @private */
export interface TreeFolder {
    /** Unique in the tree, and stable while the report is filtered. */
    id: string;
    name: string;
    counts: TreeCount[];
    folders: TreeFolder[];
    units: TreeUnit[];
}

/**
 * @private
 *
 * What layout "Split" says about a group. An entry carries `label`, so its colour is never the only sign of the
 * group.
 */
export const GROUPS: Record<FindingGroup, { label: string; one: string; many: string; meaning: string }> = {
    Break: {
        label: 'Break',
        one: 'break',
        many: 'breaks',
        meaning: 'Removes or changes something that plugins rely on.',
    },
    Addition: {
        label: 'New',
        one: 'new',
        many: 'new',
        meaning: 'Becomes public API with this change. Mark it @private if plugins should not rely on it.',
    },
    Allowed: {
        label: 'Allowed',
        one: 'allowed',
        many: 'allowed',
        meaning: 'A change that a marker announces for the next major.',
    },
    Info: {
        label: 'Info',
        one: 'info',
        many: 'info',
        meaning: 'Deprecations and resolved problems. Nothing to do.',
    },
    Private: {
        label: 'Private',
        one: 'private',
        many: 'private',
        meaning: 'Never public API.',
    },
};

const KIND_NAMES: Record<string, string> = {
    component: 'Components',
    service: 'Services',
    store: 'Stores',
    global: 'Shopware object',
    class: 'Classes',
};

/**
 * @private
 *
 * The sizes of the groups over `clusters`, the breaks first.
 */
export function countsOf(clusters: readonly Cluster[]): TreeCount[] {
    return GROUP_ORDER.flatMap((group) => {
        const size = clusters.reduce(
            (sum, cluster) => sum + (cluster.counts.find((count) => count.group === group)?.size ?? 0),
            0,
        );

        return size === 0 ? [] : [{ group, size, label: plural(size, GROUPS[group].one, GROUPS[group].many) }];
    });
}

function breaksOf(counts: readonly TreeCount[]): number {
    return counts.find(({ group }) => group === 'Break')?.size ?? 0;
}

function treeUnit(cluster: Cluster): TreeUnit {
    return { cluster, counts: countsOf([cluster]) };
}

function clustersBelow(folder: TreeFolder): Cluster[] {
    return [
        ...folder.units.map(({ cluster }) => cluster),
        ...folder.folders.flatMap(clustersBelow),
    ];
}

function folderOf(id: string, name: string, clusters: readonly Cluster[], depth: number): TreeFolder {
    const here = clusters.filter(({ folder }) => folder.length === depth);
    const names = [...new Set(clusters.filter(({ folder }) => folder.length > depth).map(({ folder }) => folder[depth]))];
    const folders = names
        .map((child) =>
            folderOf(
                `${id}/${child}`,
                child,
                clusters.filter(({ folder }) => folder[depth] === child),
                depth + 1,
            ),
        )
        .sort((one, other) => breaksOf(other.counts) - breaksOf(one.counts) || one.name.localeCompare(other.name));

    // A folder with one folder and no unit of its own is one step too many.
    if (here.length === 0 && folders.length === 1 && depth > 0) {
        return { ...folders[0], name: `${name} / ${folders[0].name}` };
    }

    return { id, name, counts: countsOf(clusters), folders, units: here.map(treeUnit) };
}

/**
 * @private
 *
 * The kinds of unit that the clusters have, each with its folders.
 */
export function treeOf(clusters: readonly Cluster[]): TreeFolder[] {
    const kinds = [...new Set(clusters.map(({ unit }) => unit.kind))].sort(
        (one, other) => Object.keys(KIND_NAMES).indexOf(one) - Object.keys(KIND_NAMES).indexOf(other),
    );

    return kinds.map((kind) =>
        folderOf(
            kind,
            KIND_NAMES[kind] ?? kind,
            clusters.filter(({ unit }) => unit.kind === kind),
            0,
        ),
    );
}

/** @private */
export function unitCount(tree: readonly TreeFolder[]): number {
    return tree.flatMap(clustersBelow).length;
}

/**
 * @private
 *
 * The tree with only the units of `kept`, by the id of the unit. The folders keep their shape and their ids, so a
 * search narrows the tree without moving what stays.
 */
export function pruneTree(tree: readonly TreeFolder[], kept: ReadonlyMap<string, Cluster>): TreeFolder[] {
    return tree.flatMap((folder) => {
        const folders = pruneTree(folder.folders, kept);
        const units = folder.units.flatMap(({ cluster }) => {
            const match = kept.get(cluster.unit.id);

            return match === undefined ? [] : [treeUnit(match)];
        });
        const pruned = { ...folder, folders, units };

        return folders.length === 0 && units.length === 0 ? [] : [{ ...pruned, counts: countsOf(clustersBelow(pruned)) }];
    });
}

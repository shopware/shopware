<template>
    <div class="split">
        <div class="split__side">
            <div class="split__search">
                <mt-search
                    v-model="query"
                    size="small"
                    placeholder="Find a component, block or method"
                />
            </div>
            <nav
                class="split__tree tree"
                aria-label="Changes by component"
            >
                <split-tree-folder
                    v-for="kind in tree"
                    :key="kind.id"
                    :folder="kind"
                    :depth="0"
                    :is-open="isOpen"
                    :picked="picked"
                    @toggle="toggle"
                    @select="select"
                />
                <p
                    v-if="tree.length === 0"
                    class="split__empty"
                >
                    Nothing matches “{{ query }}”.
                </p>
            </nav>
        </div>

        <div
            ref="detailPane"
            class="split__detail"
        >
            <split-detail
                v-if="selected"
                :cluster="selected.cluster"
                :line="selected.line"
                :head="head"
                @close="picked = null"
            />
            <split-summary
                v-else
                :counts="countsOf(allClusters)"
                :units-with-breaks="allClusters.filter(({ breaks }) => breaks > 0).length"
                :base="base"
                :head="head"
                :private-count="privateCount"
            />
        </div>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * Layout "Split" of direction "One field": a tree of the changed units on the left with its own search, and on
 * the right the summary of the comparison or what the picked entry says in full.
 */
import { MtSearch } from '@shopware-ag/meteor-component-library';
import { computed, nextTick, ref, shallowRef } from 'vue';
import { clustersOf } from '../shared/clusters';
import type { Cluster, ClusterLine } from '../shared/clusters';
import { matches } from '../shared/report';
import type { Finding } from '../shared/report';
import { countsOf, pruneTree, treeOf, unitCount } from '../shared/split-tree';
import type { TreeNode } from '../shared/split-tree';
import SplitDetail from './split-detail.vue';
import SplitSummary from './split-summary.vue';
import SplitTreeFolder from './split-tree-folder.vue';

const props = defineProps<{
    findings: Finding[];
    base: string;
    head: string;
    privateCount: number;
}>();

/** A tree with at most this many units shows all its folders open. */
const FOLDERS_OPEN_UNTIL = 20;

const query = ref('');
const isSearching = computed(() => query.value.trim() !== '');
const allClusters = computed(() => clustersOf(props.findings));
const wholeTree = computed(() => treeOf(allClusters.value));

/** A search keeps the shape of the whole tree and takes away what does not match. */
const tree = computed(() => {
    if (!isSearching.value) {
        return wholeTree.value;
    }

    const matching = clustersOf(props.findings.filter((finding) => matches(finding, query.value)));

    return pruneTree(wholeTree.value, new Map(matching.map((cluster) => [cluster.unit.id, cluster])));
});
const isSmall = computed(() => unitCount(tree.value) <= FOLDERS_OPEN_UNTIL);

const picked = shallowRef<{ unit: string; line: string } | null>(null);

/** What the reader opened or closed by hand, by the id of the folder or unit. */
const toggled = shallowRef(new Map<string, boolean>());

/**
 * A unit starts closed, so the reader first sees which units changed and how. A search that narrows the tree down
 * opens them.
 */
const openAtFirst = computed(
    (): Record<TreeNode, boolean> => ({
        kind: true,
        folder: isSmall.value,
        unit: isSmall.value && isSearching.value,
    }),
);

function isOpen(id: string, node: TreeNode): boolean {
    return toggled.value.get(id) ?? (openAtFirst.value[node] || id === picked.value?.unit);
}

function toggle(id: string, node: TreeNode): void {
    toggled.value = new Map(toggled.value).set(id, !isOpen(id, node));
}

/** The entry the reader picked, while the comparison still has it. */
const selected = computed((): { cluster: Cluster; line: ClusterLine } | null => {
    const cluster = allClusters.value.find(({ unit }) => unit.id === picked.value?.unit);
    const line = cluster?.lines.find(({ key }) => key === picked.value?.line);

    return cluster === undefined || line === undefined ? null : { cluster, line };
});

const detailPane = ref<HTMLElement | null>(null);

/** Picking the entry again closes it. A new entry starts at its head, also when the last one was scrolled down. */
function select(cluster: Cluster, line: ClusterLine): void {
    const again = picked.value?.unit === cluster.unit.id && picked.value.line === line.key;

    picked.value = again ? null : { unit: cluster.unit.id, line: line.key };
    void nextTick(() => {
        if ((detailPane.value?.getBoundingClientRect().top ?? 0) < 0) {
            detailPane.value?.scrollIntoView({ block: 'start' });
        }
    });
}
</script>

<style>
.split {
    display: grid;
    grid-template-columns: 480px minmax(0, 1fr);
    align-items: start;
    gap: 16px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.split code {
    font-size: 1em;
    line-height: 1;
}

/*
 * The tree stays in view and scrolls on its own. Its height leaves room for the fields above it at the top of the
 * page and for the review bar of the design ideas below it.
 */
.split__side {
    position: sticky;
    top: 16px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 176px);
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
    overflow: hidden;
}

/* The search belongs to the tree: it is the head of the same box and as wide as what the rows hold. */
.split__search {
    flex: none;
    padding: 8px 16px;
    border-bottom: 1px solid var(--color-border-primary-default);
}

.split__tree {
    min-height: 0;
    overflow: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    padding-bottom: 4px;
}

.split__empty {
    padding: 8px 16px;
    color: var(--color-text-secondary-default);
}

.split__detail {
    min-width: 0;
    padding: 16px;
    scroll-margin-top: 16px;
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
}
</style>

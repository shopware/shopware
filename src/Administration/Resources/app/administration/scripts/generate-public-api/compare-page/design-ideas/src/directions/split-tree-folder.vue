<template>
    <div class="tree__group">
        <split-tree-row
            :type="type"
            :name="folder.name"
            :depth="depth"
            :open="isOpen(folder.id, type)"
            :counts="folder.counts"
            @toggle="emit('toggle', folder.id, type)"
        />

        <template v-if="isOpen(folder.id, type)">
            <split-tree-folder
                v-for="child in folder.folders"
                :key="child.id"
                :folder="child"
                :depth="depth + 1"
                :is-open="isOpen"
                :picked="picked"
                @toggle="(id, node) => emit('toggle', id, node)"
                @select="(cluster, line) => emit('select', cluster, line)"
            />
            <template
                v-for="{ cluster, counts } in folder.units"
                :key="cluster.unit.id"
            >
                <split-tree-row
                    type="unit"
                    :name="cluster.unit.name"
                    :depth="depth + 1"
                    :open="isOpen(cluster.unit.id, 'unit')"
                    :counts="counts"
                    @toggle="emit('toggle', cluster.unit.id, 'unit')"
                />
                <div
                    v-if="isOpen(cluster.unit.id, 'unit')"
                    class="tree__entries"
                    :style="{ '--depth': depth + 1 }"
                >
                    <button
                        v-for="line in cluster.lines"
                        :key="line.key"
                        class="plain group-entry tree__entry"
                        type="button"
                        :data-group="line.group"
                        :aria-current="picked?.unit === cluster.unit.id && picked.line === line.key"
                        @click="emit('select', cluster, line)"
                    >
                        <span class="group-entry__label">{{ GROUPS[line.group].label }}</span>
                        <code-name
                            v-if="line.type === 'fix'"
                            class="tree__title"
                            :text="line.title"
                        />
                        <span
                            v-else
                            class="tree__title"
                        >{{ summaryOf(line) }}</span>
                        <span class="tree__pointer">
                            <mt-icon
                                name="regular-chevron-right-xs"
                                size="12px"
                            />
                        </span>
                    </button>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * One folder of the tree of layout "Split", with the folders and units below it. A unit opens to its entries.
 * An entry carries the bar, the tint and the word of its group, and the picked one points at the detail.
 */
import { MtIcon } from '@shopware-ag/meteor-component-library';
import { computed } from 'vue';
import type { Cluster, ClusterLine, NamesLine } from '../shared/clusters';
import CodeName from '../shared/code-name.vue';
import { plural } from '../shared/report';
import { GROUPS } from '../shared/split-tree';
import type { TreeFolder, TreeNode } from '../shared/split-tree';
import SplitTreeRow from './split-tree-row.vue';

const props = defineProps<{
    folder: TreeFolder;
    depth: number;
    isOpen: (id: string, node: TreeNode) => boolean;
    /** The entry the detail shows, by the id of its unit and the key of its line. */
    picked: { unit: string; line: string } | null;
}>();
const emit = defineEmits<{ toggle: [id: string, node: TreeNode]; select: [cluster: Cluster, line: ClusterLine] }>();

const type = computed((): TreeNode => (props.depth === 0 ? 'kind' : 'folder'));

function summaryOf(line: NamesLine): string {
    const kinds = line.kinds.map(({ kind, findings }) => (findings.length === 1 ? kind : `${findings.length} ${kind}`));

    return line.label === null
        ? `${plural(line.size, 'element')}: ${kinds.filter((kind) => kind !== '').join(', ')}`
        : `${line.size} ${line.label}`;
}
</script>

<style>
.tree {
    --tree-step: 12px;
    --tree-inset: 16px;
}

/* The focus ring stays inside the row, because the tree clips what reaches beyond it. */
.tree button.plain:focus-visible {
    outline-offset: -2px;
}

/* The label of an entry starts where the name of its unit starts: 3px bar and 8px padding from the box. */
.tree__entries {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 2px var(--tree-inset) 6px calc(var(--tree-inset) + var(--depth) * var(--tree-step) + 9px);
}

.tree__entry {
    grid-template-columns: 52px minmax(0, 1fr) 12px;
    align-items: start;
    line-height: var(--font-line-height-xs);
    overflow-wrap: anywhere;
}

.tree .tree__entry code {
    line-height: var(--font-line-height-xs);
}

.tree__entry:hover {
    filter: brightness(0.97);
}

/* The picked entry is framed and points at the detail beside it. Its weight stays, so its text wraps as before. */
.tree__pointer {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 22px;
    visibility: hidden;
}

.tree__entry[aria-current='true'] {
    box-shadow: inset 0 0 0 1px var(--color-text-primary-default);
}

.tree__entry[aria-current='true'] .tree__pointer {
    visibility: visible;
}
</style>

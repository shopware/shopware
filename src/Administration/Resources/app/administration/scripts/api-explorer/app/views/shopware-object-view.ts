/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, watch, type PropType } from 'vue';
import type { CompareResponse } from '../api';
import { ReasonText, SegmentedToggle, SeverityPill, TypeDiff } from '../components/common';
import type { TypeSource } from '../../snapshot/types';
import { formatCount, sideLabel } from '../model/format';
import { renderParameters } from '../model/members';
import { isLongPair } from '../model/text-diff';
import {
    allContainerPaths,
    ancestorPaths,
    countHiddenPrivate,
    countNodes,
    findObjectNode,
    mergeObjectTrees,
    visibleObjectRows,
    type MergedObjectNode,
} from '../model/object-tree';

const TYPE_SOURCES: Record<TypeSource, string> = {
    typescript: 'TypeScript',
    jsdoc: 'JSDoc in a JS file',
    inferred: 'Inferred',
    unknown: 'Unknown',
};

function changedPaths(node: MergedObjectNode | null): string[] {
    if (!node || node.changeCount === 0) {
        return [];
    }

    const below = node.children.flatMap(changedPaths);

    return node.children.some((child) => child.changeCount > 0)
        ? [
              node.path,
              ...below,
          ]
        : below;
}

export const ShopwareObjectView = defineComponent({
    name: 'ShopwareObjectView',
    components: { ReasonText, SegmentedToggle, SeverityPill, TypeDiff },
    props: {
        result: { type: Object as PropType<CompareResponse>, required: true },
        path: { type: String as PropType<string | null>, default: null },
        query: { type: String, default: '' },
    },
    setup(props) {
        const mode = ref<'everything' | 'changes'>('everything');
        const showPrivate = ref(true);
        const localQuery = ref('');

        const root = computed(() =>
            mergeObjectTrees(props.result.base.shopwareObject, props.result.head.shopwareObject, props.result.diff.changes),
        );
        const expanded = ref(new Set<string>());
        const selectedPath = ref<string | null>(null);

        const reveal = (path: string | null): void => {
            const next = new Set(expanded.value);
            changedPaths(root.value).forEach((entry) => next.add(entry));
            if (root.value) {
                next.add(root.value.path);
            }
            if (path) {
                ancestorPaths(root.value, path)
                    .slice(0, -1)
                    .forEach((entry) => next.add(entry));
            }
            expanded.value = next;
            selectedPath.value = path ?? root.value?.path ?? null;
        };
        reveal(props.path);
        watch(
            () => [
                props.path,
                root.value,
            ],
            () => reveal(props.path),
        );

        const query = computed(() =>
            [
                props.query,
                localQuery.value,
            ]
                .filter((value) => value.trim() !== '')
                .join(' ')
                .trim(),
        );

        const rows = computed(() =>
            visibleObjectRows(root.value, expanded.value, {
                changesOnly: mode.value === 'changes',
                showPrivate: showPrivate.value,
                query: query.value,
            }).map(({ node, depth }) => {
                const entry = (node.head ?? node.base)!;

                return {
                    node,
                    entry,
                    depth,
                    hasChildren: node.children.length > 0,
                    isExpanded: expanded.value.has(node.path) || query.value !== '',
                    removed: node.head === null,
                    containsChanges: node.change === null && node.changeCount > 0,
                };
            }),
        );

        const select = (node: MergedObjectNode): void => {
            const next = new Set(expanded.value);

            if (node.children.length > 0 && (selectedPath.value === node.path || !next.has(node.path))) {
                if (next.has(node.path)) {
                    next.delete(node.path);
                } else {
                    next.add(node.path);
                }
            }

            expanded.value = next;
            selectedPath.value = node.path;
        };

        const detail = computed(() => {
            const node = selectedPath.value ? findObjectNode(root.value, selectedPath.value) : null;

            if (!node) {
                return null;
            }

            const entry = (node.head ?? node.base)!;
            const signatureChanged = node.base !== null && node.head !== null && node.base.signature !== node.head.signature;

            return {
                node,
                entry,
                before: node.change?.before ?? node.base?.signature ?? null,
                after: node.change?.after ?? node.head?.signature ?? null,
                longPair: isLongPair(
                    node.change?.before ?? node.base?.signature ?? null,
                    node.change?.after ?? node.head?.signature ?? null,
                ),
                showBeforeAfter: signatureChanged || (node.change !== null && node.change.before !== node.change.after),
                typeSource: TYPE_SOURCES[entry.typeSource],
                location: entry.location ? `${entry.location.file}:${entry.location.line}` : null,
                callSignatures: entry.callSignatures.map(
                    (signature) => `(${renderParameters(signature.parameters)}) => ${signature.returnType}`,
                ),
                state: !node.head ? 'removed' : !node.base ? 'added' : null,
            };
        });

        const stats = computed(() => {
            const total = countNodes(props.result.head.shopwareObject);
            const changed = props.result.diff.changes.filter((change) => change.area === 'shopwareObject').length;

            return `${formatCount(total)} entries, ${formatCount(changed)} of them changed`;
        });

        return {
            mode,
            modes: [
                { value: 'everything', label: 'Everything' },
                { value: 'changes', label: 'Changes only' },
            ],
            showPrivate,
            localQuery,
            rows,
            selectedPath,
            select,
            detail,
            stats,
            hiddenPrivate: computed(() => (showPrivate.value ? 0 : countHiddenPrivate(root.value))),
            baseLabel: computed(() => sideLabel(props.result.base.ref)),
            headLabel: computed(() => sideLabel(props.result.head.ref)),
            expandAll: () => {
                expanded.value = new Set(allContainerPaths(root.value));
            },
            indent: (depth: number) => ({ paddingLeft: `${12 + depth * 18}px` }),
        };
    },
    template: `
        <div class="page">
            <div class="page__header">
                <h1 class="page__title">Shopware object</h1>
                <p class="muted-strong">Everything plugins can reach through <code class="inline-code">window.Shopware</code>, as a tree with signatures. {{ stats }}. Types come from TypeScript, in JS files from JSDoc or inference.</p>
            </div>

            <div class="filters">
                <label class="search search--wide">
                    <span class="visually-hidden">Search the object</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input v-model="localQuery" type="search" placeholder="Search a path, e.g. Data.Criteria">
                </label>
                <SegmentedToggle v-model="mode" :options="modes" />
                <label class="checkbox"><input v-model="showPrivate" type="checkbox">Show private</label>
                <button type="button" class="button button--small" @click="expandAll">Expand all</button>
            </div>

            <div class="object-layout">
                <section class="card object-tree" aria-label="Object tree">
                    <p v-if="rows.length === 0" class="empty">No entries match this filter.</p>
                    <p v-if="hiddenPrivate > 0" class="tree-note" data-testid="private-hidden">{{ hiddenPrivate }} private {{ hiddenPrivate === 1 ? 'entry' : 'entries' }} without public changes hidden</p>
                    <button
                        v-for="row in rows"
                        :key="row.node.path"
                        type="button"
                        class="tree-row tree-row--object"
                        :style="indent(row.depth)"
                        :aria-pressed="selectedPath === row.node.path ? 'true' : 'false'"
                        :aria-expanded="row.hasChildren ? (row.isExpanded ? 'true' : 'false') : undefined"
                        :data-path="row.node.path"
                        data-testid="object-row"
                        @click="select(row.node)"
                    >
                        <svg class="caret" :class="{ 'caret--open': row.isExpanded, 'caret--hidden': !row.hasChildren }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        <span class="tree-row__name mono" :class="{ removed: row.removed, strong: row.node.changeCount > 0 }">{{ row.node.name }}</span>
                        <span class="kind">{{ row.entry.kind }}</span>
                        <span class="tree-row__signature mono">{{ row.entry.signature }}</span>
                        <SeverityPill v-if="row.node.change" :change="row.node.change" small />
                        <span v-else-if="row.containsChanges" class="pill pill--small pill--neutral"><span class="pill__symbol" aria-hidden="true">•</span>contains changes</span>
                        <span v-if="row.entry.visibility === 'private' && row.node.change?.severity !== 'private'" class="pill pill--small" data-severity="private"><span class="pill__symbol" aria-hidden="true">∘</span>private</span>
                    </button>
                </section>

                <aside v-if="detail" class="card card--padded object-detail stack" aria-label="Details" data-testid="object-detail">
                    <div class="stack stack--tight">
                        <div class="chips">
                            <span class="kind">{{ detail.entry.kind }}</span>
                            <SeverityPill v-if="detail.node.change" :change="detail.node.change" small />
                            <span v-if="detail.entry.visibility !== 'public'" class="pill pill--small" :data-severity="detail.entry.visibility === 'private' ? 'private' : 'compatible'">{{ detail.entry.visibility }}</span>
                            <span v-if="detail.entry.deprecation" class="pill pill--small" data-severity="newlyDeprecated"><span class="pill__symbol" aria-hidden="true">↓</span>deprecated</span>
                            <span v-if="detail.state" class="chip">only in {{ detail.state === 'removed' ? baseLabel : headLabel }}</span>
                        </div>
                        <h2 class="detail-title mono">{{ detail.node.path }}</h2>
                    </div>

                    <p v-if="detail.node.change" class="note">
                        <ReasonText v-for="(reason, index) in detail.node.change.reasons" :key="index" :text="reason" class="reason" />
                    </p>

                    <TypeDiff
                        v-if="detail.showBeforeAfter && detail.longPair"
                        :before="detail.before"
                        :after="detail.after"
                        :before-label="'Before (' + baseLabel + ')'"
                        :after-label="'After (' + headLabel + ')'"
                    />
                    <div v-else-if="detail.showBeforeAfter" class="stack stack--tight">
                        <span class="eyebrow eyebrow--before">Before ({{ baseLabel }})</span>
                        <code class="signature signature--before">{{ detail.before ?? '—' }}</code>
                        <span class="eyebrow eyebrow--after">After ({{ headLabel }})</span>
                        <code class="signature signature--after">{{ detail.after ?? '—' }}</code>
                    </div>
                    <div v-else class="stack stack--tight">
                        <span class="eyebrow">Signature</span>
                        <code class="signature">{{ detail.entry.signature }}</code>
                    </div>

                    <dl class="facts">
                        <dt>Kind</dt><dd>{{ detail.entry.kind }}</dd>
                        <dt>Visibility</dt><dd>{{ detail.entry.visibility }}</dd>
                        <dt>Deprecation</dt>
                        <dd>{{ detail.entry.deprecation ? ((detail.entry.deprecation.version ? detail.entry.deprecation.version + ' – ' : '') + detail.entry.deprecation.message) : '—' }}</dd>
                        <dt>Type source</dt><dd>{{ detail.typeSource }}</dd>
                        <dt>Location</dt><dd class="mono">{{ detail.location ?? '—' }}</dd>
                        <template v-if="detail.entry.truncated"><dt>Note</dt><dd>The walk stopped at this node because of a depth or size limit.</dd></template>
                    </dl>

                    <div v-if="detail.callSignatures.length > 0" class="stack stack--tight">
                        <span class="eyebrow">Call signatures</span>
                        <code v-for="(signature, index) in detail.callSignatures" :key="index" class="signature">{{ signature }}</code>
                    </div>
                </aside>
            </div>
        </div>
    `,
});

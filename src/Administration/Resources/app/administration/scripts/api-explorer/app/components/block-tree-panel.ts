/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, watch, type PropType } from 'vue';
import type { ApiChange } from '../../diff/types';
import type { ComponentEntry } from '../../snapshot/types';
import { mergeBlockTrees, withChangedOnly, type MergedBlock } from '../model/block-tree';
import { LineDiff, ReasonText, SeverityPill } from './common';

function isMoved(block: MergedBlock): boolean {
    return block.base !== null && block.head !== null && block.chainBefore.join('/') !== block.chainAfter.join('/');
}

export const BlockTreePanel = defineComponent({
    name: 'BlockTreePanel',
    components: { SeverityPill, ReasonText, LineDiff },
    props: {
        base: { type: Object as PropType<ComponentEntry | null>, default: null },
        head: { type: Object as PropType<ComponentEntry | null>, default: null },
        changes: { type: Array as PropType<ApiChange[]>, required: true },
        changesOnly: { type: Boolean, default: false },
        /** Id of the block to select first. */
        initialBlock: { type: String as PropType<string | null>, default: null },
        baseLabel: { type: String, required: true },
        headLabel: { type: String, required: true },
    },
    setup(props) {
        const merged = computed(() => mergeBlockTrees(props.base?.blocks ?? [], props.head?.blocks ?? [], props.changes));
        const blockChanges = computed(() => merged.value.filter((block) => block.change !== null));

        const rows = computed(() => (props.changesOnly ? withChangedOnly(merged.value) : merged.value));

        const selectedId = ref<string | null>(null);
        const pickDefault = (): void => {
            const preferred = props.initialBlock ?? blockChanges.value[0]?.id ?? merged.value[0]?.id ?? null;
            selectedId.value = merged.value.some((block) => block.id === preferred)
                ? preferred
                : (merged.value[0]?.id ?? null);
        };
        pickDefault();
        watch(
            () => [
                props.initialBlock,
                merged.value,
            ],
            pickDefault,
        );

        const selected = computed(() => merged.value.find((block) => block.id === selectedId.value) ?? null);

        const summary = computed(() => {
            const counts = { removed: 0, added: 0, moved: 0, content: 0 };

            merged.value.forEach((block) => {
                if (!block.head) {
                    counts.removed += 1;
                } else if (!block.base) {
                    counts.added += 1;
                } else {
                    counts.moved += isMoved(block) ? 1 : 0;
                    counts.content += block.base.contentHash !== block.head.contentHash ? 1 : 0;
                }
            });

            const parts = [`${props.base?.blocks.length ?? 0} → ${props.head?.blocks.length ?? 0}`];
            if (counts.removed) parts.push(`${counts.removed} removed`);
            if (counts.added) parts.push(`${counts.added} added`);
            if (counts.moved) parts.push(`${counts.moved} moved`);
            if (counts.content) parts.push(`${counts.content} content ${counts.content === 1 ? 'change' : 'changes'}`);

            return parts.join(' · ');
        });

        const detail = computed(() => {
            const block = selected.value;

            if (!block || !rows.value.includes(block)) {
                return null;
            }

            const status = !block.head ? 'removed' : !block.base ? 'added' : 'present';

            return {
                block,
                moved: isMoved(block),
                status,
                file: props.head?.templateFile ?? props.base?.templateFile ?? '',
                diffTitle:
                    block.base && block.head && block.base.contentHash === block.head.contentHash
                        ? 'Content (unchanged)'
                        : 'Content diff',
            };
        });

        return {
            merged,
            rows,
            summary,
            selectedId,
            detail,
            hiddenCount: computed(() => merged.value.length - rows.value.length),
            indent: (block: MergedBlock) => ({ paddingLeft: `${12 + block.depth * 16}px` }),
        };
    },
    template: `
        <section class="card" aria-labelledby="blocks-title">
            <div class="card__header">
                <h2 id="blocks-title" class="card__title card__title--small">Twig blocks <span class="muted normal">{{ summary }}</span></h2>
                <span class="muted small">Select a block for details</span>
            </div>
            <p v-if="merged.length === 0" class="empty">This component has no Twig blocks.</p>
            <div v-else class="split">
                <div class="split__tree" role="list" aria-label="Block tree">
                    <div v-for="block in rows" :key="block.id" role="listitem">
                        <button
                            type="button"
                            class="tree-row"
                            :style="indent(block)"
                            :aria-pressed="selectedId === block.id ? 'true' : 'false'"
                            :data-block="block.id"
                            @click="selectedId = block.id"
                        >
                            <span class="tree-row__name mono" :class="{ removed: !block.head, strong: block.change }">{{ block.name }}</span>
                            <SeverityPill v-if="block.change" :change="block.change" small />
                        </button>
                    </div>
                    <p v-if="hiddenCount > 0" class="tree-note">{{ hiddenCount }} {{ hiddenCount === 1 ? 'block' : 'blocks' }} hidden. Switch to "Everything" to browse them.</p>
                </div>

                <p v-if="!detail" class="split__detail muted">No block selected.</p>
                <div v-else class="split__detail" data-testid="block-detail">
                    <div class="stack stack--tight">
                        <SeverityPill v-if="detail.block.change" :change="detail.block.change" />
                        <span v-else class="pill pill--neutral">unchanged</span>
                        <h3 class="detail-title mono">{{ detail.block.name }}</h3>
                        <p v-if="detail.block.change">
                            <ReasonText v-for="(reason, index) in detail.block.change.reasons" :key="index" :text="reason" class="reason" />
                        </p>
                    </div>

                    <div v-if="detail.moved" class="chains" data-testid="parent-chains">
                        <div class="chain chain--before">
                            <span class="chain__label">Before ({{ baseLabel }})</span>
                            <span v-for="(name, index) in detail.block.chainBefore" :key="index" class="chain__item mono" :style="{ paddingLeft: (index * 12) + 'px' }">{{ name }}</span>
                        </div>
                        <div class="chain chain--after">
                            <span class="chain__label">After ({{ headLabel }})</span>
                            <span v-for="(name, index) in detail.block.chainAfter" :key="index" class="chain__item mono" :style="{ paddingLeft: (index * 12) + 'px' }">{{ name }}</span>
                        </div>
                    </div>

                    <LineDiff
                        :before="detail.block.base ? detail.block.base.content : null"
                        :after="detail.block.head ? detail.block.head.content : null"
                        :title="detail.diffTitle"
                        :caption="detail.file"
                    />
                </div>
            </div>
        </section>
    `,
});

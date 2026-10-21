/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, type PropType } from 'vue';
import type { CompareResponse } from '../api';
import { ChangesPanel } from '../components/changes-panel';
import { formatCount } from '../model/format';
import type { ChangeFilter } from '../model/severity';

export const BlocksView = defineComponent({
    name: 'BlocksView',
    components: { ChangesPanel },
    props: {
        result: { type: Object as PropType<CompareResponse>, required: true },
        query: { type: String, default: '' },
    },
    setup(props) {
        const filter = ref<ChangeFilter | null>(null);
        const changes = computed(() => props.result.diff.changes.filter((change) => change.kind === 'block'));
        const blockCount = computed(() =>
            formatCount(props.result.head.components.reduce((sum, component) => sum + component.blocks.length, 0)),
        );

        return { filter, changes, blockCount };
    },
    template: `
        <div class="page">
            <div class="page__header">
                <h1 class="page__title">Twig blocks</h1>
                <p class="muted-strong">{{ blockCount }} blocks across all component templates. Block changes per component, open one to see the block tree and the content diff.</p>
            </div>
            <ChangesPanel v-model:filter="filter" :changes="changes" :query="query" />
        </div>
    `,
});

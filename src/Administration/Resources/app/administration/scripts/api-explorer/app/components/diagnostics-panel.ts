/**
 * @sw-package framework
 */

import { computed, defineComponent, type PropType } from 'vue';
import type { SnapshotDiagnostic } from '../../snapshot/types';

export interface DiagnosticGroup {
    side: string;
    entries: SnapshotDiagnostic[];
}

const MAX_ENTRIES = 50;

export const DiagnosticsPanel = defineComponent({
    name: 'DiagnosticsPanel',
    props: {
        groups: { type: Array as PropType<DiagnosticGroup[]>, required: true },
    },
    setup(props) {
        const visibleGroups = computed(() =>
            props.groups
                .filter((group) => group.entries.length > 0)
                .map((group) => ({
                    ...group,
                    shown: group.entries.slice(0, MAX_ENTRIES).map((entry, index) => ({ ...entry, key: index })),
                    hidden: Math.max(0, group.entries.length - MAX_ENTRIES),
                })),
        );
        const total = computed(() => props.groups.reduce((sum, group) => sum + group.entries.length, 0));

        return { visibleGroups, total };
    },
    template: `
        <section v-if="total > 0" class="diagnostics" aria-labelledby="diagnostics-title">
            <h2 id="diagnostics-title" class="diagnostics__title">Diagnostics <span class="muted">{{ total }}</span></h2>
            <p class="small">The extraction was incomplete. Missing entries can show up as removals or additions.</p>
            <details class="diagnostics__details">
                <summary>Show the messages</summary>
                <div v-for="(group, index) in visibleGroups" :key="index" class="diagnostics__group">
                    <h3 class="diagnostics__side">{{ group.side }}</h3>
                    <ul class="diagnostics__list">
                        <li v-for="entry in group.shown" :key="entry.key">
                            <span v-if="entry.file" class="mono">{{ entry.file }}: </span>{{ entry.message }}
                        </li>
                        <li v-if="group.hidden > 0" class="muted">… and {{ group.hidden }} more</li>
                    </ul>
                </div>
            </details>
        </section>
    `,
});

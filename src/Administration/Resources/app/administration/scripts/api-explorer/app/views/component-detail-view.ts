/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, type PropType } from 'vue';
import type { CompareResponse } from '../api';
import { BlockTreePanel } from '../components/block-tree-panel';
import { ReasonText, SegmentedToggle, SeverityPill, TypeDiff } from '../components/common';
import type { ComponentEntry } from '../../snapshot/types';
import { sideLabel } from '../model/format';
import { MEMBER_SECTIONS, memberRows } from '../model/members';
import { isLongPair } from '../model/text-diff';
import { countByPill } from '../model/severity';
import { hrefFor } from '../router';

const LANGUAGE_LABELS: Record<ComponentEntry['language'], string> = {
    js: 'JavaScript',
    ts: 'TypeScript',
    vue: 'Vue SFC',
};

export const ComponentDetailView = defineComponent({
    name: 'ComponentDetailView',
    components: { BlockTreePanel, ReasonText, SegmentedToggle, SeverityPill, TypeDiff },
    props: {
        result: { type: Object as PropType<CompareResponse>, required: true },
        name: { type: String, required: true },
        block: { type: String as PropType<string | null>, default: null },
    },
    setup(props) {
        const mode = ref<'changes' | 'everything'>('changes');

        const base = computed(() => props.result.base.components.find((component) => component.name === props.name) ?? null);
        const head = computed(() => props.result.head.components.find((component) => component.name === props.name) ?? null);
        const entry = computed(() => head.value ?? base.value);
        const changes = computed(() =>
            props.result.diff.changes.filter((change) => change.area === 'component' && change.owner === props.name),
        );
        const componentChange = computed(() => changes.value.find((change) => change.kind === 'component') ?? null);

        const header = computed(() => {
            const component = entry.value;

            if (!component) {
                return null;
            }

            return {
                visibility: component.visibility,
                registration:
                    component.registration === 'extend'
                        ? `Component.extend ← ${component.extends ?? '?'}`
                        : 'Component.register',
                packageName: component.packageName,
                language: LANGUAGE_LABELS[component.language],
                deprecation: component.deprecation,
                files: [
                    component.sourceFile,
                    component.templateFile,
                    ...component.overriddenBy,
                ].filter((file): file is string => file !== null),
                state: !head.value ? 'removed' : !base.value ? 'added' : null,
            };
        });

        const summary = computed(() => countByPill(changes.value));

        const sections = computed(() =>
            MEMBER_SECTIONS.map((section) => {
                const all = memberRows(section, base.value, head.value, changes.value);
                const rows = mode.value === 'changes' ? all.filter((row) => row.change !== null) : all;

                return {
                    ...section,
                    rows,
                    total: all.length,
                    changed: all.filter((row) => row.change !== null).length,
                    hidden: all.length - rows.length,
                };
            }),
        );

        return {
            mode,
            modes: [
                { value: 'changes', label: 'Changes only' },
                { value: 'everything', label: 'Everything' },
            ],
            base,
            head,
            header,
            changes,
            componentChange,
            summary,
            shownSections: computed(() => sections.value.filter((section) => section.rows.length > 0)),
            quietSections: computed(() => sections.value.filter((section) => section.rows.length === 0)),
            baseLabel: computed(() => sideLabel(props.result.base.ref)),
            headLabel: computed(() => sideLabel(props.result.head.ref)),
            listHref: hrefFor({ name: 'components' }),
            isLongPair,
        };
    },
    template: `
        <div class="page">
            <nav aria-label="Breadcrumb" class="breadcrumb">
                <a :href="listHref">Components</a>
                <span aria-hidden="true">/</span>
                <span class="mono" aria-current="page">{{ name }}</span>
            </nav>

            <p v-if="!header" class="empty card">The component <code class="inline-code">{{ name }}</code> exists in neither snapshot.</p>

            <template v-else>
                <section class="card card--padded stack">
                    <div class="detail-header">
                        <div class="stack">
                            <h1 class="detail-header__name mono" :class="{ removed: header.state === 'removed' }">{{ name }}</h1>
                            <div class="chips">
                                <span class="visibility" :data-visibility="header.visibility">{{ header.visibility }}</span>
                                <span class="chip mono">{{ header.registration }}</span>
                                <span v-if="header.packageName" class="chip mono">@sw-package {{ header.packageName }}</span>
                                <span class="chip">{{ header.language }}</span>
                                <span v-if="header.state" class="chip">only in {{ header.state === 'removed' ? baseLabel : headLabel }}</span>
                                <span v-if="header.deprecation" class="pill" data-severity="newlyDeprecated"><span class="pill__symbol" aria-hidden="true">↓</span>deprecated{{ header.deprecation.version ? ' ' + header.deprecation.version : '' }}</span>
                            </div>
                            <div class="files mono">
                                <span v-for="file in header.files" :key="file">{{ file }}</span>
                            </div>
                        </div>
                        <SegmentedToggle v-model="mode" :options="modes" />
                    </div>
                    <div class="chips">
                        <SeverityPill v-for="chip in summary" :key="chip.key" :change="chip.change" :count="chip.count" />
                        <span v-if="summary.length === 0" class="pill pill--neutral">unchanged</span>
                    </div>
                    <p v-if="componentChange" class="note">
                        <ReasonText v-for="(reason, index) in componentChange.reasons" :key="index" :text="reason" class="reason" />
                    </p>
                </section>

                <BlockTreePanel
                    :base="base"
                    :head="head"
                    :changes="changes"
                    :changes-only="mode === 'changes'"
                    :initial-block="block"
                    :base-label="baseLabel"
                    :head-label="headLabel"
                />

                <section v-for="section in shownSections" :key="section.kind" class="card" :aria-label="section.title">
                    <div class="card__header">
                        <h2 class="card__title card__title--small">{{ section.title }}
                            <span class="muted normal">{{ section.total }} · {{ section.changed }} changed</span>
                        </h2>
                    </div>
                    <div class="table-scroll">
                        <table class="table table--members">
                            <thead>
                                <tr><th scope="col">Status</th><th scope="col">Name</th><th scope="col">Before ({{ baseLabel }})</th><th scope="col">After ({{ headLabel }})</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in section.rows" :key="row.name" data-testid="member-row">
                                    <td>
                                        <SeverityPill v-if="row.change" :change="row.change" />
                                        <span v-else class="pill pill--neutral">unchanged</span>
                                    </td>
                                    <td class="mono strong" :class="{ removed: !row.head }">{{ row.name }}</td>
                                    <td v-if="isLongPair(row.before, row.after)" colspan="2">
                                        <TypeDiff :before="row.before" :after="row.after" :before-label="'Before (' + baseLabel + ')'" :after-label="'After (' + headLabel + ')'" />
                                        <div v-if="row.change" class="reasons small">
                                            <ReasonText v-for="(reason, index) in row.change.reasons" :key="index" :text="reason" class="reason" />
                                        </div>
                                    </td>
                                    <template v-else>
                                    <td><code class="code-cell" :class="{ 'code-cell--before': row.change }">{{ row.before ?? '—' }}</code></td>
                                    <td>
                                        <code class="code-cell" :class="{ 'code-cell--after': row.change }">{{ row.after ?? '—' }}</code>
                                        <div v-if="row.change" class="reasons small">
                                            <ReasonText v-for="(reason, index) in row.change.reasons" :key="index" :text="reason" class="reason" />
                                        </div>
                                    </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="section.hidden > 0" class="table-note">{{ section.hidden }} unchanged hidden</p>
                    </div>
                </section>

                <section v-if="quietSections.length > 0" class="card card--padded stack stack--tight">
                    <h2 class="card__title card__title--small">Other options <span class="muted normal">no changes</span></h2>
                    <div class="chips">
                        <span v-for="section in quietSections" :key="section.kind" class="chip">{{ section.title.toLowerCase() }} <strong>{{ section.total }}</strong></span>
                    </div>
                </section>
            </template>
        </div>
    `,
});

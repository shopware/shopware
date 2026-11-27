<template>
    <div class="units">
        <form
            class="units__bar"
            @submit.prevent="submit"
        >
            <strong class="units__name">Public API check</strong>
            <mt-text-field
                v-model="pullRequest"
                class="units__pr"
                size="small"
                placeholder="Pull request"
                aria-label="Pull request"
                @update:model-value="clearRevisions"
            />
            <span class="units__muted">or</span>
            <mt-text-field
                v-model="base"
                class="units__revision"
                size="small"
                placeholder="Base, e.g. v6.7.0.0"
                aria-label="Base"
                @update:model-value="pullRequest = ''"
            />
            <mt-text-field
                v-model="head"
                class="units__revision"
                size="small"
                placeholder="Head, e.g. trunk"
                aria-label="Head"
                @update:model-value="pullRequest = ''"
            />
            <mt-button
                type="submit"
                variant="primary"
                size="small"
                :is-loading="state.kind === 'loading'"
            >
                Compare
            </mt-button>
            <span
                v-if="report"
                class="units__muted units__commits"
            >
                {{ report.base.slice(0, 12) }} → {{ report.head.slice(0, 12) }} · generator {{ GENERATOR }}
            </span>
        </form>

        <div
            v-if="state.kind === 'loading'"
            class="units__notice"
        >
            <mt-progress-bar
                :label="progress.message"
                :model-value="progress.loaded"
                :max-value="progress.total"
            />
        </div>

        <div
            v-if="state.kind === 'error'"
            class="units__notice"
        >
            <mt-banner
                :variant="state.reason === 'limit' ? 'attention' : 'critical'"
                :title="state.message"
            >
                <template v-if="state.reason === 'unknown'">
                    Check the number in the field above.
                </template>
                <form
                    v-else
                    class="units__token"
                    @submit.prevent="emit('compare', state.request)"
                >
                    <mt-password-field
                        v-model="token"
                        size="small"
                        placeholder="GitHub token"
                        aria-label="GitHub token"
                    />
                    <mt-button
                        type="submit"
                        size="small"
                        variant="secondary"
                    >
                        Compare with token
                    </mt-button>
                    <span class="units__muted">The token lifts the limit and only goes to api.github.com.</span>
                </form>
            </mt-banner>
        </div>

        <div
            v-if="state.kind === 'start'"
            class="units__empty"
        >
            <mt-empty-state
                icon="regular-layer-group"
                headline="Pick a comparison"
                description="Enter a pull request, or a base and a head: two branches, tags or commits. The page then lists every changed component, service and store."
            />
        </div>

        <div
            v-if="state.kind === 'loading' || report"
            class="units__panes"
        >
            <aside class="units__index">
                <div class="units__index-head">
                    <mt-search
                        v-model="query"
                        size="small"
                        placeholder="Find a component, service or store"
                        :disabled="!report"
                    />
                    <mt-switch
                        v-model="withPrivate"
                        label="Private changes"
                        :disabled="!report"
                    />
                </div>
                <ul
                    v-if="report"
                    class="units__list"
                >
                    <li
                        v-for="unit in shownUnits"
                        :key="unit.id"
                    >
                        <button
                            class="plain units__item"
                            :class="{ 'is--selected': unit.id === selectedId }"
                            type="button"
                            @click="selectedId = unit.id"
                        >
                            <span class="units__item-name">
                                <code>{{ unit.name }}</code>
                                <span
                                    v-if="unit.kind !== 'component'"
                                    class="units__muted"
                                >{{ unit.kind }}</span>
                            </span>
                            <mt-badge
                                v-if="unit.breaks > 0"
                                size="s"
                                variant="critical"
                            >
                                {{ unit.breaks }}
                            </mt-badge>
                            <span
                                v-if="unit.findings.length > unit.breaks"
                                class="units__muted"
                            >{{ unit.findings.length - unit.breaks }}</span>
                        </button>
                    </li>
                    <li
                        v-if="shownUnits.length === 0"
                        class="units__muted units__none"
                    >
                        Nothing matches “{{ query }}”.
                    </li>
                </ul>
                <div
                    v-else
                    class="units__skeleton"
                >
                    <mt-skeleton-bar
                        v-for="index in 8"
                        :key="index"
                    />
                </div>
            </aside>

            <main
                v-if="selected"
                class="units__detail"
            >
                <header>
                    <mt-text
                        as="h1"
                        size="xl"
                        weight="semibold"
                    >
                        <code>{{ selected.name }}</code>
                    </mt-text>
                    <mt-text color="color-text-secondary-default">
                        {{ selected.kind }} · {{ selectedSummary }}
                    </mt-text>
                </header>
                <section
                    v-for="section in selectedSections"
                    :key="section.group"
                    class="units__section"
                >
                    <mt-text
                        as="h2"
                        size="s"
                        weight="semibold"
                        color="color-text-secondary-default"
                        class="units__section-title"
                    >
                        {{ section.title }} · {{ section.findings.length }}
                    </mt-text>
                    <article
                        v-for="finding in section.findings"
                        :key="finding.element + finding.rule"
                        class="units__finding"
                        :class="`is--${finding.group.toLowerCase()}`"
                    >
                        <div class="units__finding-head">
                            <code class="units__member">{{ memberText(finding) }}</code>
                            <span class="units__muted">{{ finding.rule }}</span>
                            <span
                                v-if="finding.at"
                                class="at-end"
                            >
                                <mt-link
                                    as="a"
                                    type="external"
                                    target="_blank"
                                    :to="sourceUrl(report.head, finding.at)"
                                >
                                    {{ shortAt(finding.at) }}
                                </mt-link>
                            </span>
                        </div>
                        <p>
                            {{ finding.explanation }}
                            <template v-if="stateNote(finding)">
                                Holds {{ stateNote(finding) }}.
                            </template>
                        </p>
                        <p v-if="finding.fix">
                            <strong>Fix:</strong> <fix-text :fix="finding.fix" />
                        </p>
                        <p
                            v-if="finding.possibleRename || finding.alsoAffects"
                            class="units__muted"
                        >
                            <template v-if="finding.possibleRename">
                                Possibly renamed to <code>{{ finding.possibleRename }}</code>.
                            </template>
                            <template v-if="finding.alsoAffects">
                                Also affects {{ finding.alsoAffects.join(', ') }}.
                            </template>
                        </p>
                    </article>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * Direction "Components": an index of the changed units beside the findings of the one you pick.
 */
import {
    MtBadge,
    MtBanner,
    MtButton,
    MtEmptyState,
    MtLink,
    MtPasswordField,
    MtProgressBar,
    MtSearch,
    MtSkeletonBar,
    MtSwitch,
    MtText,
    MtTextField,
} from '@shopware-ag/meteor-component-library';
import { computed, ref } from 'vue';
import FixText from '../shared/fix-text.vue';
import { GROUP_ORDER, groupBy, memberText, shortAt, sourceUrl, stateNote, unitOf } from '../shared/report';
import type { Finding, FindingGroup } from '../shared/report';
import { GENERATOR } from '../shared/states';
import type { CompareRequest, Progress, ViewState } from '../shared/states';

const props = defineProps<{ state: ViewState; progress: Progress }>();
const emit = defineEmits<{ compare: [request: CompareRequest] }>();

const request = props.state.kind === 'start' ? null : props.state.request;
const pullRequest = ref(request !== null && 'pullRequest' in request ? String(request.pullRequest) : '');
const base = ref(request !== null && 'base' in request ? request.base : '');
const head = ref(request !== null && 'head' in request ? request.head : '');
const token = ref('');

function clearRevisions(): void {
    base.value = '';
    head.value = '';
}

function submit(): void {
    if (pullRequest.value.trim() !== '') {
        emit('compare', { pullRequest: Number(pullRequest.value.replace(/\D/g, '')) });
    } else if (base.value.trim() !== '' && head.value.trim() !== '') {
        emit('compare', { base: base.value.trim(), head: head.value.trim() });
    }
}

const report = computed(() => (props.state.kind === 'report' ? props.state.report : null));
const query = ref('');
const withPrivate = ref(false);

interface UnitEntry {
    id: string;
    kind: string;
    name: string;
    breaks: number;
    findings: Finding[];
}

const units = computed<UnitEntry[]>(() => {
    const findings = (report.value?.findings ?? []).filter(({ group }) => withPrivate.value || group !== 'Private');

    return [...groupBy(findings, (finding) => unitOf(finding).id).entries()]
        .map(([id, unitFindings]) => ({
            ...unitOf(unitFindings[0]),
            id,
            breaks: unitFindings.filter(({ group }) => group === 'Break').length,
            findings: unitFindings,
        }))
        .sort((one, other) => other.breaks - one.breaks || one.name.localeCompare(other.name));
});

const shownUnits = computed(() => {
    const words = query.value.toLowerCase().split(/\s+/).filter(Boolean);

    return units.value.filter((unit) => words.every((word) => unit.name.toLowerCase().includes(word)));
});

const selectedId = ref<string | null>(null);
const selected = computed(
    () => shownUnits.value.find(({ id }) => id === selectedId.value) ?? shownUnits.value[0] ?? null,
);

const TITLES: Record<FindingGroup, string> = {
    Break: 'Breaks',
    Addition: 'Additions',
    Allowed: 'Allowed changes',
    Info: 'Info',
    Private: 'Private changes',
};

const selectedSections = computed(() =>
    GROUP_ORDER.map((group) => ({
        group,
        title: TITLES[group],
        findings: selected.value?.findings.filter((finding) => finding.group === group) ?? [],
    })).filter(({ findings }) => findings.length > 0),
);

const selectedSummary = computed(() =>
    selectedSections.value.map(({ title, findings }) => `${findings.length} ${title.toLowerCase()}`).join(', '),
);

</script>

<style>
.units {
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.units__bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: var(--color-background-primary-default);
    border-bottom: 1px solid var(--color-border-primary-default);
}

.units__name {
    margin-right: 16px;
    white-space: nowrap;
}

.units__pr {
    width: 130px;
    flex: none;
}

.units__revision {
    width: 190px;
    flex: none;
}

.units__commits {
    margin-left: auto;
    font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
    font-size: var(--font-size-xs);
}

.units__muted {
    color: var(--color-text-secondary-default);
}

.units__notice {
    padding: 16px 24px 0;
}

.units__token {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.units__token .mt-field {
    width: 260px;
}

.units__empty {
    padding: 64px 24px;
}

.units__panes {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    align-items: start;
    flex: 1;
}

.units__index {
    position: sticky;
    top: 53px;
    max-height: calc(100vh - 53px - 56px);
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--color-border-primary-default);
    background: var(--color-background-primary-default);
}

.units__index-head {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid var(--color-border-primary-default);
}

.units__index-head > *,
.units__index-head .mt-field {
    margin: 0;
}

.units__list {
    list-style: none;
    margin: 0;
    padding: 8px;
    overflow-y: auto;
}

.units__item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: var(--border-radius-xs);
}

.units__item:hover {
    background: var(--color-interaction-secondary-hover);
}

.units__item.is--selected {
    background: var(--color-background-brand-default, #e0ebff);
    box-shadow: inset 3px 0 0 var(--color-border-brand-selected);
}

.units__item-name {
    flex: 1;
    min-width: 0;
    display: flex;
    gap: 6px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.units__item-name code {
    overflow: hidden;
    text-overflow: ellipsis;
}

.units__none {
    padding: 8px;
}

.units__skeleton {
    padding: 16px;
}

.units__detail {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 24px 32px;
    max-width: 960px;
}

.units__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.units__section-title {
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.units__finding {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px 16px;
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-left: 3px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
}

.units__finding.is--break {
    border-left-color: var(--color-border-critical-default);
}

.units__finding.is--addition {
    border-left-color: var(--color-border-positive-default);
}

.units__finding.is--allowed {
    border-left-color: var(--color-border-attention-default);
}

.units__finding-head {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 4px 12px;
}

.units__member {
    font-weight: 600;
    overflow-wrap: anywhere;
}

</style>

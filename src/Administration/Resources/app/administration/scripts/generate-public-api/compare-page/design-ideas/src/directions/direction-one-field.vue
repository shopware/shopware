<template>
    <main
        class="one"
        :class="{ 'is--wide': layout === 'table' || layout === 'split' }"
    >
        <form
            class="one__compare"
            @submit.prevent="submit"
        >
            <mt-text-field
                v-model="text"
                aria-label="Comparison"
                placeholder="Pull request, or base...head"
                :error="fieldError"
            />
            <mt-button
                type="submit"
                variant="primary"
                size="large"
                :is-loading="state.kind === 'loading'"
            >
                Compare
            </mt-button>
            <mt-text
                v-if="state.kind === 'start'"
                class="one__wide"
                size="xs"
                color="color-text-secondary-default"
            >
                Lists what changes for plugins between two states of the Administration. For example
                <mt-link
                    as="button"
                    @click.prevent="text = '20959'"
                >
                    20959
                </mt-link>,
                a pull request link, or
                <mt-link
                    as="button"
                    @click.prevent="text = 'v6.7.0.0...trunk'"
                >
                    v6.7.0.0...trunk
                </mt-link>.
            </mt-text>
            <template v-if="state.kind === 'error' && state.reason === 'limit'">
                <mt-password-field
                    v-model="token"
                    class="one__token"
                    aria-label="GitHub token"
                    placeholder="GitHub token"
                />
                <mt-text
                    class="one__wide"
                    size="xs"
                    color="color-text-secondary-default"
                >
                    A token lifts the limit. It stays in this tab and is only sent to api.github.com.
                </mt-text>
            </template>
        </form>

        <mt-progress-bar
            v-if="state.kind === 'loading'"
            class="one__progress"
            :label="progress.message"
            :model-value="progress.loaded"
            :max-value="progress.total"
        />

        <one-field-split
            v-if="report && layout === 'split'"
            :findings="listed"
            :base="report.base"
            :head="report.head"
            :private-count="privateCount"
        />
        <template v-else-if="report">
            <div class="one__narrow">
                <mt-search
                    v-model="query"
                    size="small"
                    placeholder="Narrow down: a component, block, rule or any word"
                />
                <p
                    v-if="layout === 'clusters'"
                    class="one__counts"
                >
                    <template
                        v-for="(count, index) in counts"
                        :key="count.word"
                    >
                        <span v-if="index > 0"> · </span>
                        <mt-link
                            as="button"
                            :variant="count.group === 'Break' && count.size > 0 ? 'critical' : 'primary'"
                            @click="query = query === count.word ? '' : count.word"
                        >
                            {{ count.label }}
                        </mt-link>
                    </template>
                </p>
            </div>

            <template v-if="layout === 'clusters'">
                <template
                    v-for="section in sections"
                    :key="section.module"
                >
                    <h2
                        v-if="sectioned"
                        class="one__module"
                    >
                        <code>{{ section.module }}</code>
                        <span
                            v-if="section.breaks > 0"
                            class="one__module-breaks"
                        >{{ plural(section.breaks, 'break') }}</span>
                    </h2>
                    <div class="one__clusters">
                        <one-field-cluster
                            v-for="cluster in section.clusters"
                            :key="cluster.unit.id"
                            :cluster="cluster"
                            :head="report.head"
                            :open="isOpen(cluster)"
                            @toggle="toggle(cluster)"
                        />
                    </div>
                </template>
            </template>
            <component
                :is="LAYOUTS[layout]"
                v-else-if="matching.length > 0"
                :findings="matching"
                :head="report.head"
            />

            <p
                v-if="matching.length === 0"
                class="one__muted one__small"
            >
                Nothing matches “{{ query }}”.
            </p>
            <mt-button
                v-if="layout === 'clusters' && clusters.length > visible"
                class="one__more"
                variant="secondary"
                @click="visible += PAGE"
            >
                Show {{ Math.min(PAGE, clusters.length - visible) }} more of {{ plural(clusters.length - visible, 'component') }}
            </mt-button>
            <p class="one__muted one__small">
                Compared {{ report.base.slice(0, 12) }} with {{ report.head.slice(0, 12) }}, with the generator of {{ GENERATOR }}.
                <template v-if="hasAdditions">
                    Additions become public API with this change. Mark them @private if plugins should not rely on them.
                </template>
                <template v-if="privateCount > 0">
                    {{ plural(privateCount, 'change') }} to private elements are not listed. They are never public API.
                </template>
            </p>
        </template>
    </main>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * Direction "One field": one field for the comparison, one for narrowing, and the findings clustered by the
 * component, service or store they are about.
 */
import {
    MtButton,
    MtLink,
    MtPasswordField,
    MtProgressBar,
    MtSearch,
    MtText,
    MtTextField,
} from '@shopware-ag/meteor-component-library';
import { computed, ref, shallowRef, watch } from 'vue';
import { clustersOf, sectionsOf } from '../shared/clusters';
import type { Cluster, ClusterSection } from '../shared/clusters';
import { GROUP_ORDER, countBy, matches, plural } from '../shared/report';
import { GENERATOR, requestFromText, requestText } from '../shared/states';
import type { CompareRequest, Progress, ViewState } from '../shared/states';
import OneFieldCluster from './one-field-cluster.vue';
import OneFieldSplit from './one-field-split.vue';
import OneFieldTable from './one-field-table.vue';
import OneFieldTabs from './one-field-tabs.vue';

const props = withDefaults(
    defineProps<{ state: ViewState; progress: Progress; layout?: 'clusters' | 'tabs' | 'table' | 'split' }>(),
    { layout: 'clusters' },
);

/** The layouts that replace the clustered list and take its findings, see `one-field-layouts.ts`. */
const LAYOUTS = { tabs: OneFieldTabs, table: OneFieldTable };
const emit = defineEmits<{ compare: [request: CompareRequest] }>();

/** How many clusters a click on "Show more" adds. */
const PAGE = 40;
/** A report with more clusters gets the module folders as headings. */
const SECTIONS_FROM = 20;
/** A report with at most this many clusters shows all of them open, a larger one only those with breaks. */
const ALL_OPEN_UNTIL = 12;

const text = ref(props.state.kind === 'start' ? '' : requestText(props.state.request));
const token = ref('');
const invalid = ref(false);

const fieldError = computed(() => {
    if (invalid.value) {
        return { code: 1, detail: 'Enter a pull request number or link, or two revisions as base...head.' };
    }

    return props.state.kind === 'error' ? { code: 1, detail: props.state.message } : null;
});

function submit(): void {
    const request = requestFromText(text.value);

    invalid.value = request === null;

    if (request !== null) {
        emit('compare', request);
    }
}

const report = computed(() => (props.state.kind === 'report' ? props.state.report : null));
const listed = computed(() =>
    (report.value?.findings ?? [])
        .filter(({ group }) => group !== 'Private')
        .sort((one, other) => GROUP_ORDER.indexOf(one.group) - GROUP_ORDER.indexOf(other.group)),
);
const privateCount = computed(() => (report.value?.findings ?? []).length - listed.value.length);

const WORDS = { Break: ['break', 'breaks'], Addition: ['addition', 'additions'], Allowed: ['allowed', 'allowed'], Info: ['info', 'info'] } as const;

const counts = computed(() => {
    const sizes = countBy(listed.value, ({ group }) => group);

    return (['Break', 'Addition', 'Allowed', 'Info'] as const)
        .filter((group) => group === 'Break' || sizes.has(group))
        .map((group) => {
            const size = sizes.get(group) ?? 0;

            return { group, size, word: WORDS[group][0], label: plural(size, WORDS[group][0], WORDS[group][1]) };
        });
});

const query = ref('');
const visible = ref(PAGE);
const matching = computed(() => listed.value.filter((finding) => matches(finding, query.value)));
const hasAdditions = computed(() => matching.value.some(({ group }) => group === 'Addition'));
const clusters = computed(() => clustersOf(matching.value));
const sectioned = computed(() => clusters.value.length > SECTIONS_FROM);

/** The first `visible` clusters, in sections when the report has enough of them. */
const sections = computed((): ClusterSection[] => {
    const all = sectioned.value ? sectionsOf(clusters.value) : [{ module: '', breaks: 0, clusters: clusters.value }];
    let left = visible.value;

    return all.flatMap((section) => {
        const shown = section.clusters.slice(0, left);

        left -= shown.length;

        return shown.length === 0 ? [] : [{ ...section, clusters: shown }];
    });
});

const toggled = shallowRef(new Map<string, boolean>());

function isOpen(cluster: Cluster): boolean {
    return toggled.value.get(cluster.unit.id) ?? (cluster.breaks > 0 || clusters.value.length <= ALL_OPEN_UNTIL);
}

function toggle(cluster: Cluster): void {
    toggled.value = new Map(toggled.value).set(cluster.unit.id, !isOpen(cluster));
}

watch(query, () => {
    visible.value = PAGE;
    toggled.value = new Map();
});
</script>

<style>
.one {
    max-width: 980px;
    margin: 0 auto;
    padding: 32px 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* The field column ends where the button starts, in every state, so nothing jumps between them. */
/* The table and the split view need the room for their second and third column. */
.one.is--wide {
    max-width: 1280px;
}

.one__compare {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 120px;
    align-items: start;
    gap: 16px;
}

/* The button fills its column, so the field ends on a whole pixel. */
.one__compare > .mt-button {
    width: 100%;
}

.one__wide {
    grid-column: 1 / -1;
}

.one__token {
    grid-column: 1;
}

.one__progress {
    width: 100%;
}

/* Meteor reserves room below a field, which would push the report away from the comparison. */
.one .mt-field {
    margin-bottom: 0;
}

.one__narrow {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}

.one__counts,
.one__small {
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.one__muted {
    color: var(--color-text-secondary-default);
}

.one__clusters {
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
    overflow: hidden;
}

/* Stands closer to the clusters below it than to the ones above, and starts where their names start. */
.one__module {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: 16px;
    margin-bottom: -8px;
    padding: 0 17px;
    font-size: var(--font-size-2xs);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-2xs);
    color: var(--color-text-secondary-default);
}

.one__module code {
    font-size: inherit;
    line-height: 1;
}

.one__module-breaks {
    font-weight: var(--font-weight-regular);
    color: var(--color-text-critical-default);
}

.one__more {
    align-self: flex-start;
}
</style>

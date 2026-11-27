<template>
    <article class="detail">
        <header class="detail__head">
            <div class="detail__names">
                <code-name
                    class="detail__muted"
                    :text="cluster.unit.name"
                />
                <h3 class="detail__title">
                    <template v-if="line.type === 'names'">
                        {{ line.size }} {{ line.label ?? (line.size === 1 ? 'new element' : 'new elements') }}
                    </template>
                    <template v-else-if="line.headline">
                        <template
                            v-for="(piece, index) in line.headline.split(/(?<=_)/)"
                            :key="index"
                        >{{ piece }}<wbr></template>
                    </template>
                    <code-name
                        v-else
                        :text="line.title"
                    />
                </h3>
                <p
                    v-if="line.type === 'fix'"
                    class="detail__muted"
                >
                    {{ line.group }} · {{ line.rule }}
                </p>
            </div>
            <mt-button
                class="detail__close"
                size="small"
                variant="secondary"
                square
                aria-label="Back to the summary"
                @click="emit('close')"
            >
                <mt-icon
                    name="regular-times-s"
                    size="12px"
                />
            </mt-button>
        </header>

        <template v-if="line.type === 'names'">
            <p
                v-if="line.group === 'Addition'"
                class="detail__muted"
            >
                Plugins can rely on these from now on. Mark them @private if they are not meant for plugins.
            </p>
            <kind-names
                :kinds="line.kinds"
                :head="head"
                :label="line.label"
            />
        </template>

        <!-- The break check has neither code nor a plugin example for this entry, so its texts stand alone. -->
        <fix-detail
            v-else-if="parts.length === 1"
            :line="line"
            :head="head"
            :base="base"
        />

        <template v-else>
            <p>
                <template v-if="line.places.length > 1">
                    For example:
                </template>
                {{ first.explanation }}
                <template v-if="stateNote(first)">
                    Holds {{ stateNote(first) }}.
                </template>
            </p>
            <mt-tabs
                class="detail__tabs"
                small
                :items="tabs"
                :default-item="parts[0]"
                @new-item-active="tab = $event"
            />

            <code-diff
                v-if="shown === 'changed' && first.code?.changed"
                :block="first.code.changed"
            />

            <code-diff
                v-else-if="shown === 'plugin' && example"
                :block="example.block"
                :marked="example.broken"
            />

            <template v-else>
                <section
                    v-if="first.fix || first.code?.fix"
                    class="detail__section"
                >
                    <p v-if="first.fix">
                        <fix-text
                            :fix="first.fix"
                            :copyable="!first.code?.fix"
                        />
                    </p>
                    <code-diff
                        v-if="first.code?.fix"
                        :block="first.code.fix"
                        copyable
                        wrapped
                    />
                </section>
                <section
                    v-if="hasPlaces"
                    class="detail__section"
                >
                    <h4 v-if="first.fix">
                        Where
                    </h4>
                    <fix-places
                        :places="line.places"
                        :head="head"
                        :base="base"
                    />
                </section>
            </template>
        </template>
    </article>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * The detail side of layout "Split" for one entry. A break says what changed in a sentence, and shows the changed
 * code, plugin code that it breaks and the fix one at a time, each only when the break check has it.
 */
import { MtButton, MtIcon, MtTabs } from '@shopware-ag/meteor-component-library';
import { computed, ref } from 'vue';
import type { Cluster, ClusterLine } from '../shared/clusters';
import CodeDiff from '../shared/code-diff.vue';
import CodeName from '../shared/code-name.vue';
import FixDetail from '../shared/fix-detail.vue';
import FixPlaces from '../shared/fix-places.vue';
import FixText from '../shared/fix-text.vue';
import KindNames from '../shared/kind-names.vue';
import { stateNote } from '../shared/report';
import type { CodeBlock } from '../shared/report';

type Part = 'changed' | 'plugin' | 'fix';

const props = defineProps<{ cluster: Cluster; line: ClusterLine; base: string; head: string }>();
const emit = defineEmits<{ close: [] }>();


/** A line of several places shows the code of its first place, and lists all of them with the fix. */
const first = computed(() => (props.line.type === 'fix' ? props.line.places[0].finding : undefined));

const example = computed((): { block: CodeBlock; broken: number } | undefined => {
    const code = first.value?.detail.example;

    return (
        code && {
            block: {
                file: `${code.language === 'twig' ? 'Plugin template' : 'Plugin script'} · the marked line breaks`,
                lines: code.lines.map((text) => ({ type: ' ', text })),
            },
            broken: code.broken,
        }
    );
});

/** The last part is always there, because it lists the places. A change that a marker announced has no fix. */
const parts = computed((): Part[] => [
    ...(first.value?.code?.changed ? (['changed'] as const) : []),
    ...(example.value ? (['plugin'] as const) : []),
    'fix',
]);

const LABELS: Record<Part, string> = { changed: 'What changed', plugin: 'In a plugin', fix: 'Fix' };

const tabs = computed(() =>
    parts.value.map((name) => ({ name, label: name === 'fix' && !first.value?.fix ? 'Where' : LABELS[name] })),
);

const hasPlaces = computed(
    () =>
        props.line.type === 'fix' &&
        props.line.places.some(({ name, finding }) => name || finding.at || finding.detail.atBase || finding.possibleRename),
);

const tab = ref<Part | null>(null);
const shown = computed(() => tab.value ?? parts.value[0]);
</script>

<style>
.detail {
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.detail code {
    font-size: 1em;
}

.detail__head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: 12px;
}

.detail__names {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.detail__title {
    font-size: var(--font-size-m);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-m);
    overflow-wrap: anywhere;
}

/* A name of the sources may be longer than a line. It breaks behind an underscore, and anywhere only as a last resort. */
.detail p,
.detail .fix-text {
    overflow-wrap: anywhere;
}

.detail .detail__title code {
    line-height: var(--font-line-height-m);
    vertical-align: top;
}

.detail__muted {
    color: var(--color-text-secondary-default);
}

/*
 * The tabs of Meteor centre themselves, and here they start where the text starts. A tab has its own room above
 * its label, which the gap of the detail would add to.
 */
.detail .detail__tabs {
    align-self: stretch;
    width: auto;
    margin: -8px 0 0;
}

.detail .detail__tabs .mt-tabs__item:first-of-type {
    padding-left: 0;
}

/* Without code below it, a marker of the break check stands below its sentence and breaks between its words. */
.detail .fix-detail .fix-text__marker {
    display: block;
    margin-top: 4px;
}

.detail .fix-detail .fix-text__marker code {
    overflow-wrap: break-word;
}

.detail__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* With the code of the fix below it, a marker is a part of its sentence and wraps with it. */
.detail__section .fix-text__marker {
    display: inline;
}

.detail__section h4 {
    margin: 0;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-xs);
}
</style>

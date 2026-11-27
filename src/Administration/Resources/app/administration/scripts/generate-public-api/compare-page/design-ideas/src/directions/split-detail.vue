<template>
    <article class="detail">
        <header class="detail__head">
            <div class="detail__names">
                <code-name
                    class="detail__muted"
                    :text="cluster.unit.name"
                />
                <h3 class="detail__title">
                    <template v-if="example">
                        {{ example.headline }}
                    </template>
                    <code-name
                        v-else-if="line.type === 'fix'"
                        :text="line.title"
                    />
                    <template v-else>
                        {{ line.size }} {{ line.label ?? (line.size === 1 ? 'new element' : 'new elements') }}
                    </template>
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

        <!-- The break check reports no code for this entry yet, so its texts stand alone. -->
        <fix-detail
            v-else-if="!example"
            :line="line"
            :head="head"
        />

        <template v-else>
            <p>{{ example.summary }}</p>
            <mt-tabs
                class="detail__tabs"
                small
                :items="TABS"
                :default-item="tab"
                @new-item-active="tab = $event"
            />

            <code-diff
                v-if="tab === 'changed'"
                :block="example.changed"
            />

            <section
                v-else-if="tab === 'plugin'"
                class="detail__section"
            >
                <p>{{ example.pluginEffect }}</p>
                <code-diff :block="example.plugin" />
            </section>

            <template v-else>
                <section class="detail__section">
                    <ol class="detail__steps">
                        <li
                            v-for="step in example.steps"
                            :key="step"
                        >
                            {{ step }}
                        </li>
                    </ol>
                    <code-diff
                        :block="example.fix"
                        copyable
                    />
                </section>
                <section class="detail__section">
                    <h4>Where</h4>
                    <fix-places
                        :places="line.places"
                        :head="head"
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
 * The detail side of layout "Split" for one entry. A break says what is changing in a sentence, and shows the
 * changed code, what a plugin runs into and the fix one at a time.
 */
import { MtButton, MtIcon, MtTabs } from '@shopware-ag/meteor-component-library';
import { computed, ref, watch } from 'vue';
import type { Cluster, ClusterLine } from '../shared/clusters';
import CodeDiff from '../shared/code-diff.vue';
import CodeName from '../shared/code-name.vue';
import { detailExampleOf } from '../shared/detail-examples';
import FixDetail from '../shared/fix-detail.vue';
import FixPlaces from '../shared/fix-places.vue';
import KindNames from '../shared/kind-names.vue';

type Part = 'changed' | 'plugin' | 'fix';

const props = defineProps<{ cluster: Cluster; line: ClusterLine; head: string }>();
const emit = defineEmits<{ close: [] }>();

const TABS: Array<{ name: Part; label: string }> = [
    { name: 'changed', label: 'What changed' },
    { name: 'plugin', label: 'In a plugin' },
    { name: 'fix', label: 'Fix' },
];

const tab = ref<Part>('changed');

watch(
    () => props.line,
    () => {
        tab.value = 'changed';
    },
);

const example = computed(() =>
    props.line.type === 'fix' ? detailExampleOf(props.cluster.unit.id, props.line.rule) : undefined,
);
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

/* A marker of the break check is long, so it stands below its sentence and breaks between its words. */
.detail .fix-text__marker {
    display: block;
    margin-top: 4px;
}

.detail .fix-text__marker code {
    overflow-wrap: break-word;
}

.detail__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail__section h4 {
    margin: 0;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-xs);
}

.detail .detail__steps {
    margin: 0;
    padding-left: 16px;
    list-style: decimal;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail .detail__steps li::marker {
    font-variant-numeric: tabular-nums;
}
</style>

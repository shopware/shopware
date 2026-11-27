<template>
    <section class="cluster">
        <button
            class="plain cluster__head"
            type="button"
            :aria-expanded="open"
            @click="emit('toggle')"
        >
            <span class="cluster__unit">
                <code class="cluster__name">{{ cluster.unit.name }}</code>
                <span
                    v-if="cluster.unit.kind !== 'component'"
                    class="cluster__muted"
                >{{ cluster.unit.kind }}</span>
            </span>
            <span class="cluster__counts">
                <template
                    v-for="(count, index) in cluster.counts"
                    :key="count.group"
                >
                    <span v-if="index > 0"> · </span>
                    <span :class="{ 'is--break': count.group === 'Break' }">{{ count.label }}</span>
                </template>
            </span>
            <span class="cluster__chevron">
                <mt-icon
                    :name="open ? 'regular-chevron-down-xs' : 'regular-chevron-right-xs'"
                    size="12px"
                />
            </span>
        </button>

        <ul
            v-if="open"
            class="cluster__lines"
        >
            <li
                v-for="line in cluster.lines"
                :key="line.key"
            >
                <template v-if="line.type === 'fix'">
                    <button
                        class="plain cluster__line"
                        type="button"
                        :aria-expanded="opened.has(line.key)"
                        @click="toggle(line.key)"
                    >
                        <mt-badge
                            size="s"
                            :variant="BADGE_VARIANT[line.group]"
                        >
                            {{ line.group }}
                        </mt-badge>
                        <code class="cluster__title">{{ line.title }}</code>
                        <span class="cluster__rule">{{ line.rule }}</span>
                        <span class="cluster__chevron">
                            <mt-icon
                                :name="opened.has(line.key) ? 'regular-chevron-down-xs' : 'regular-chevron-right-xs'"
                                size="12px"
                            />
                        </span>
                    </button>
                    <div
                        v-if="opened.has(line.key)"
                        class="cluster__detail"
                    >
                        <p>
                            <template v-if="line.places.length > 1">
                                For example:
                            </template>
                            {{ line.places[0].finding.explanation }}
                            <template v-if="stateNote(line.places[0].finding)">
                                Holds {{ stateNote(line.places[0].finding) }}.
                            </template>
                        </p>
                        <p v-if="line.places[0].finding.fix">
                            <strong>Fix:</strong> <fix-text :fix="line.places[0].finding.fix" />
                        </p>
                        <ul class="cluster__places">
                            <li
                                v-for="{ finding, name } in line.places"
                                :key="finding.element"
                            >
                                <span class="cluster__place">
                                    <code v-if="name">{{ name }}</code>
                                    <span
                                        v-if="finding.possibleRename"
                                        class="cluster__muted"
                                    > possibly renamed to <code>{{ finding.possibleRename }}</code></span>
                                    <span
                                        v-if="finding.alsoAffects"
                                        class="cluster__muted"
                                    > also affects {{ finding.alsoAffects.join(', ') }}</span>
                                </span>
                                <mt-link
                                    v-if="finding.at"
                                    class="cluster__source"
                                    as="a"
                                    type="external"
                                    target="_blank"
                                    :to="sourceUrl(head, finding.at)"
                                >
                                    {{ shortAt(finding.at) }}
                                </mt-link>
                            </li>
                        </ul>
                    </div>
                </template>
                <div
                    v-else
                    class="cluster__line"
                >
                    <mt-badge
                        size="s"
                        :variant="BADGE_VARIANT[line.group]"
                    >
                        {{ line.group }}
                    </mt-badge>
                    <p class="cluster__names">
                        <span
                            v-if="line.label"
                            class="cluster__muted cluster__label"
                        >{{ line.label }}:</span>
                        <template
                            v-for="kind in shownKinds(line)"
                            :key="kind.kind"
                        >
                            <span class="cluster__kind">
                                <span
                                    v-if="kind.kind !== '' && !line.label?.includes(kind.kind)"
                                    class="cluster__muted cluster__label"
                                >{{ kind.kind }}</span>
                            <template
                                v-for="(finding, index) in kind.findings"
                                :key="finding.element"
                            >
                                <template v-if="index > 0">, </template>
                                <a
                                    v-if="finding.at"
                                    class="cluster__named"
                                    target="_blank"
                                    rel="noopener"
                                    :href="sourceUrl(head, finding.at)"
                                ><code>{{ nameOf(finding) }}</code></a>
                                <code v-else>{{ nameOf(finding) }}</code>
                            </template>
                            </span>
                            <wbr>
                        </template>
                        <button
                            v-if="hiddenNames(line) > 0"
                            class="plain cluster__more"
                            type="button"
                            @click="toggle(line.key)"
                        >
                            +{{ hiddenNames(line) }} more
                        </button>
                    </p>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * One cluster of direction "One field": a component, service or store with its findings as few lines.
 */
import { MtBadge, MtIcon, MtLink } from '@shopware-ag/meteor-component-library';
import { shallowRef } from 'vue';
import { partName } from '../shared/clusters';
import type { Cluster, NamesLine } from '../shared/clusters';
import FixText from '../shared/fix-text.vue';
import { BADGE_VARIANT, membersOf, shortAt, sourceUrl, stateNote, unitOf } from '../shared/report';
import type { Finding } from '../shared/report';

defineProps<{ cluster: Cluster; head: string; open: boolean }>();
const emit = defineEmits<{ toggle: [] }>();

/** A line of names shows this many until it is opened, so it stays below the breaks in weight. */
const NAMES_SHOWN = 8;

/** The fix lines that show their detail and the name lines that show every name, by the key of the line. */
const opened = shallowRef(new Set<string>());

function toggle(key: string): void {
    const next = new Set(opened.value);

    if (!next.delete(key)) {
        next.add(key);
    }

    opened.value = next;
}

function nameOf(finding: Finding): string {
    const part = membersOf(finding).at(-1);

    return part === undefined ? `the whole ${unitOf(finding).kind}` : partName(part);
}

function namesIn(line: NamesLine): number {
    return line.kinds.reduce((sum, { findings }) => sum + findings.length, 0);
}

function hiddenNames(line: NamesLine): number {
    return opened.value.has(line.key) ? 0 : Math.max(0, namesIn(line) - NAMES_SHOWN);
}

function shownKinds(line: NamesLine): NamesLine['kinds'] {
    let left = hiddenNames(line) === 0 ? namesIn(line) : NAMES_SHOWN;

    return line.kinds.flatMap(({ kind, findings }) => {
        const shown = findings.slice(0, left);

        left -= shown.length;

        return shown.length === 0 ? [] : [{ kind, findings: shown }];
    });
}
</script>

<style>
.cluster {
    --cluster-inset: 16px;
    --cluster-gap: 12px;
    --cluster-badge: 66px;
    --cluster-chevron: 12px;
    --cluster-row: 24px;

    font-size: var(--font-size-xs);
}

/* One size for prose and names, and no taller line where both share one. */
.cluster code {
    font-size: 1em;
    line-height: 1;
}

.cluster + .cluster {
    border-top: 1px solid var(--color-border-primary-default);
}

.cluster__head {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto var(--cluster-chevron);
    align-items: center;
    column-gap: var(--cluster-gap);
    padding: 10px var(--cluster-inset);
    line-height: var(--cluster-row);
}

.cluster__head:hover,
.cluster__line:is(button):hover {
    background: var(--color-interaction-secondary-hover);
}

.cluster__unit {
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
}

.cluster__name {
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
}

.cluster__muted,
.cluster__counts,
.cluster__rule {
    color: var(--color-text-secondary-default);
}

.cluster__counts,
.cluster__rule {
    white-space: nowrap;
}

.cluster__counts .is--break {
    color: var(--color-text-critical-default);
    font-weight: var(--font-weight-medium);
}

/* A cell as high as the first row of its line, so the icon stays there when the title wraps. */
.cluster__chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    height: var(--cluster-row);
    color: var(--color-text-secondary-default);
}

.cluster__lines,
.cluster__places {
    list-style: none;
    margin: 0;
    padding: 0;
}

.cluster__lines {
    border-top: 1px solid var(--color-border-primary-default);
    padding: 4px 0;
}

.cluster__line {
    width: 100%;
    display: grid;
    grid-template-columns: var(--cluster-badge) minmax(0, 1fr) auto var(--cluster-chevron);
    align-items: start;
    column-gap: var(--cluster-gap);
    padding: 6px var(--cluster-inset);
    line-height: var(--cluster-row);
}

/* A badge keeps the size Meteor gives it and sits in the middle of the first row. */
.cluster__line .mt-badge {
    justify-self: start;
    margin-top: 2px;
}

/* A title is a cell of its own, so it takes the height of the row again. */
.cluster code.cluster__title {
    line-height: var(--cluster-row);
    overflow-wrap: anywhere;
}

/* Ends where the rule ids end, not below the chevrons. */
.cluster__names {
    grid-column: 2 / 4;
    overflow-wrap: anywhere;
}

.cluster__label {
    margin-right: 0.5ch;
}

/* The groups are set apart by room instead of a separator, which could end up alone at a line end. */
.cluster__kind {
    margin-right: 16px;
}

.cluster__named {
    color: inherit;
    text-decoration: none;
}

.cluster__named:hover,
.cluster__more {
    color: var(--color-text-brand-default);
}

.cluster__named:hover,
.cluster__more:hover {
    text-decoration: underline;
}

.cluster__detail {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 0 calc(var(--cluster-inset) + var(--cluster-chevron) + var(--cluster-gap)) 6px
        calc(var(--cluster-inset) + var(--cluster-badge) + var(--cluster-gap));
    line-height: var(--font-line-height-xs);
}

.cluster__places {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cluster__places > li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: var(--cluster-gap);
}

.cluster__place {
    overflow-wrap: anywhere;
}

.cluster__source {
    white-space: nowrap;
}
</style>

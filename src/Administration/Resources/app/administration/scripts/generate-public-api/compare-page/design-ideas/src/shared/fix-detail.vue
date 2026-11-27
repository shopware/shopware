<template>
    <div class="fix-detail">
        <p>
            <template v-if="line.places.length > 1">
                For example:
            </template>
            {{ first.explanation }}
            <template v-if="stateNote(first)">
                Holds {{ stateNote(first) }}.
            </template>
        </p>
        <p v-if="first.fix">
            <strong>Fix:</strong> <fix-text :fix="first.fix" />
        </p>
        <ul class="fix-detail__places">
            <li
                v-for="{ finding, name } in line.places"
                :key="finding.element"
                :class="{ 'is--unnamed': !name && !finding.possibleRename && !finding.alsoAffects }"
            >
                <span class="fix-detail__place">
                    <code-name
                        v-if="name"
                        :text="name"
                    />
                    <span
                        v-if="finding.possibleRename"
                        class="fix-detail__muted"
                    > possibly renamed to <code>{{ finding.possibleRename }}</code></span>
                    <span
                        v-if="finding.alsoAffects"
                        class="fix-detail__muted"
                    > also affects {{ finding.alsoAffects.join(', ') }}</span>
                </span>
                <mt-link
                    v-if="finding.at"
                    class="fix-detail__source"
                    as="a"
                    type="external"
                    target="_blank"
                    :to="sourceUrl(head, finding.at)"
                >
                    {{ name ? `line ${lineOf(finding.at)}` : shortAt(finding.at) }}
                </mt-link>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * What a fix line says when it is read in full: why it is a break, the fix, and the places with their source.
 */
import { MtLink } from '@shopware-ag/meteor-component-library';
import { computed } from 'vue';
import type { FixLine } from './clusters';
import CodeName from './code-name.vue';
import FixText from './fix-text.vue';
import { shortAt, sourceUrl, stateNote } from './report';

const props = defineProps<{ line: FixLine; head: string }>();

const first = computed(() => props.line.places[0].finding);

/** A place that has a name of its own only needs the line beside it, the file is the one of its component. */
function lineOf(at: string): string {
    return at.slice(at.lastIndexOf(':') + 1);
}
</script>

<style>
.fix-detail {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.fix-detail code {
    font-size: 1em;
    line-height: 1;
}

.fix-detail__places {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.fix-detail__places > li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: 12px;
}

/* A place without a name is its link alone, which then starts where the text starts. */
.fix-detail__places > li.is--unnamed {
    grid-template-columns: minmax(0, 1fr);
    justify-items: start;
}

.fix-detail__places > li.is--unnamed .fix-detail__place {
    display: none;
}

.fix-detail strong {
    font-weight: var(--font-weight-semibold);
}

.fix-detail__place {
    overflow-wrap: anywhere;
}

.fix-detail__muted {
    color: var(--color-text-secondary-default);
}

.fix-detail__source {
    white-space: nowrap;
}
</style>

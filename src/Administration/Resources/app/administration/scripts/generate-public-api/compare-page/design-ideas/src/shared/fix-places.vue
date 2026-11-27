<template>
    <ul class="fix-places">
        <li
            v-for="{ finding, name } in places"
            :key="finding.element"
            :class="{ 'is--unnamed': !name && !finding.possibleRename && !finding.alsoAffects }"
        >
            <span class="fix-places__place">
                <code-name
                    v-if="name"
                    :text="name"
                />
                <span
                    v-if="finding.possibleRename"
                    class="fix-places__muted"
                > possibly renamed to <code>{{ finding.possibleRename }}</code></span>
                <span
                    v-if="finding.alsoAffects"
                    class="fix-places__muted"
                > also affects {{ finding.alsoAffects.join(', ') }}</span>
            </span>
            <mt-link
                v-if="finding.at"
                class="fix-places__source"
                as="a"
                type="external"
                target="_blank"
                :to="sourceUrl(head, finding.at)"
            >
                {{ name ? `line ${lineOf(finding.at)}` : shortAt(finding.at) }}
            </mt-link>
        </li>
    </ul>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * The places of a fix line, each with a link to its source.
 */
import { MtLink } from '@shopware-ag/meteor-component-library';
import type { FixLine } from './clusters';
import CodeName from './code-name.vue';
import { shortAt, sourceUrl } from './report';

defineProps<{ places: FixLine['places']; head: string }>();

/** A place that has a name of its own only needs the line beside it, the file is the one of its component. */
function lineOf(at: string): string {
    return at.slice(at.lastIndexOf(':') + 1);
}
</script>

<style>
.fix-places {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.fix-places code {
    font-size: 1em;
    line-height: 1;
}

.fix-places > li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: 12px;
}

/* A place without a name is its link alone, which then starts where the text starts. */
.fix-places > li.is--unnamed {
    grid-template-columns: minmax(0, 1fr);
    justify-items: start;
}

.fix-places > li.is--unnamed .fix-places__place {
    display: none;
}

.fix-places__place {
    overflow-wrap: anywhere;
}

.fix-places__muted {
    color: var(--color-text-secondary-default);
}

.fix-places__source {
    white-space: nowrap;
}
</style>

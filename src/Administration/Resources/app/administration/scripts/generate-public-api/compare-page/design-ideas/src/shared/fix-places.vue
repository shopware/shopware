<template>
    <ul class="fix-places">
        <li
            v-for="{ finding, name, link } in rows"
            :key="finding.element"
            :class="{ 'is--unnamed': !name }"
        >
            <span
                v-if="name || finding.possibleRename || finding.alsoAffects"
                class="fix-places__place"
            >
                <code-name
                    v-if="name"
                    :text="name"
                />
                <span
                    v-if="finding.possibleRename"
                    class="fix-places__muted"
                > possibly renamed to <code-name :text="finding.possibleRename" /></span>
                <span
                    v-if="finding.alsoAffects"
                    class="fix-places__muted"
                > also affects {{ finding.alsoAffects.join(', ') }}</span>
            </span>
            <mt-link
                v-if="link"
                class="fix-places__source"
                as="a"
                type="external"
                target="_blank"
                :to="link.url"
            >
                {{ link.text }}
            </mt-link>
        </li>
    </ul>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * The places of a fix line, each with a link to its source. An element that is gone has no line at the head
 * commit, so its link goes to the line it had at the base commit, when the view knows that commit.
 */
import { MtLink } from '@shopware-ag/meteor-component-library';
import { computed } from 'vue';
import type { FixLine } from './clusters';
import CodeName from './code-name.vue';
import { shortAt, sourceUrl } from './report';

const props = defineProps<{ places: FixLine['places']; head: string; base?: string }>();

/** A place that has a name of its own only needs the line beside it, the file is the one of its component. */
function linkText(at: string, name: string | null): string {
    return name ? `line ${at.slice(at.lastIndexOf(':') + 1)}` : shortAt(at);
}

const rows = computed(() =>
    props.places.map(({ finding, name }) => {
        const { atBase, isGone } = finding.detail;

        if (isGone && atBase && props.base) {
            const was = `${atBase.file}:${atBase.line}`;

            return { finding, name, link: { url: sourceUrl(props.base, was), text: `was at ${linkText(was, name)}` } };
        }

        return {
            finding,
            name,
            link: finding.at ? { url: sourceUrl(props.head, finding.at), text: linkText(finding.at, name) } : null,
        };
    }),
);
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

/* A place without a name is its link, which starts where the text starts, with what else it says below. */
.fix-places > li.is--unnamed {
    grid-template-columns: minmax(0, 1fr);
    justify-items: start;
}

.fix-places > li.is--unnamed .fix-places__source {
    order: -1;
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

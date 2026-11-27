<template>
    <dl class="kind-names">
        <template
            v-for="kind in kinds"
            :key="kind.kind"
        >
            <dt v-if="isLabelled(kind.kind)">
                {{ kind.kind }}
            </dt>
            <dd :class="{ 'is--wide': !isLabelled(kind.kind) }">
                <template
                    v-for="(finding, index) in kind.findings"
                    :key="finding.element"
                >
                    <template v-if="index > 0">, </template>
                    <a
                        v-if="finding.at"
                        class="kind-names__link"
                        target="_blank"
                        rel="noopener"
                        :href="sourceUrl(head, finding.at)"
                    ><code-name :text="nameOf(finding)" /></a>
                    <code-name
                        v-else
                        :text="nameOf(finding)"
                    />
                </template>
            </dd>
        </template>
    </dl>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * The names of elements by their kind, one row per kind, each name linked to its source line.
 */
import { partName } from './clusters';
import CodeName from './code-name.vue';
import type { NamesLine } from './clusters';
import { membersOf, sourceUrl, unitOf } from './report';
import type { Finding } from './report';

const props = defineProps<{ kinds: NamesLine['kinds']; head: string; label?: string | null }>();

/** A kind needs no label when the line is about a whole unit, or when the label of the line already names it. */
function isLabelled(kind: string): boolean {
    return kind !== '' && !props.label?.includes(kind);
}

function nameOf(finding: Finding): string {
    const part = membersOf(finding).at(-1);

    return part === undefined ? `the whole ${unitOf(finding).kind}` : partName(part);
}
</script>

<style>
.kind-names {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 4px 12px;
    margin: 0;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.kind-names dt {
    color: var(--color-text-secondary-default);
}

.kind-names dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.kind-names dd.is--wide {
    grid-column: 1 / -1;
}

.kind-names code {
    font-size: 1em;
    line-height: 1;
}

.kind-names__link {
    color: inherit;
    text-decoration: none;
}

.kind-names__link:hover {
    color: var(--color-text-brand-default);
    text-decoration: underline;
}
</style>

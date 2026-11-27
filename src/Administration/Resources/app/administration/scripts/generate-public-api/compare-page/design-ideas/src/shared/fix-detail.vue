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
        <fix-places
            :places="line.places"
            :head="head"
        />
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * What a fix line says when it is read in full: why it is a break, the fix, and the places with their source.
 */
import { computed } from 'vue';
import type { FixLine } from './clusters';
import FixPlaces from './fix-places.vue';
import FixText from './fix-text.vue';
import { stateNote } from './report';

const props = defineProps<{ line: FixLine; head: string }>();

const first = computed(() => props.line.places[0].finding);
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

.fix-detail strong {
    font-weight: var(--font-weight-semibold);
}
</style>

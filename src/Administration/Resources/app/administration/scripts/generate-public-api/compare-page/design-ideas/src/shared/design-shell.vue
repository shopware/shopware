<template>
    <div class="design-shell">
        <component
            :is="direction.component"
            :key="name"
            :state="state"
            :progress="progress"
            @compare="compare"
        />
        <nav
            class="design-shell__bar"
            aria-label="Design review"
        >
            <a
                class="design-shell__back"
                href="index.html"
            >All directions</a>
            <strong>{{ direction.name }}</strong>
            <span class="design-shell__states">
                <mt-button
                    v-for="option in STATES"
                    :key="option.name"
                    size="x-small"
                    :variant="option.name === name ? 'primary' : 'secondary'"
                    @click="show(option.name)"
                >
                    {{ option.label }}
                </mt-button>
            </span>
        </nav>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * Wraps one direction with the bar that switches its states. The bar is a review tool, not part of a design.
 */
import { MtButton } from '@shopware-ag/meteor-component-library';
import type { Direction } from '../directions';
import { STATES, useDesignState } from './states';

defineProps<{ direction: Direction }>();

const { name, state, progress, show, compare } = useDesignState();
</script>

<style>
.design-shell {
    min-height: 100vh;
    padding-bottom: 72px;
}

.design-shell__bar {
    position: fixed;
    inset: auto 0 0;
    z-index: 100;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 16px;
    padding: 10px 16px;
    background: #1d2433;
    color: #fff;
    font-size: 12px;
}

.design-shell__back {
    color: #9fc1ff;
}

.design-shell__states {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
</style>

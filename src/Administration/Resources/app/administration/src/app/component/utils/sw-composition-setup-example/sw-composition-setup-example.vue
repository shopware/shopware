<template>
<section class="sw-composition-setup-example">
    <h2 class="sw-composition-setup-example__headline">
        {{ headline }}
    </h2>

    <dl class="sw-composition-setup-example__state">
        <div>
            <dt>Count</dt>
            <dd data-testid="sw-composition-setup-example-count">
                {{ count }}
            </dd>
        </div>

        <div>
            <dt>Doubled</dt>
            <dd data-testid="sw-composition-setup-example-doubled">
                {{ doubled }}
            </dd>
        </div>
    </dl>

    <p
        class="sw-composition-setup-example__note"
        data-testid="sw-composition-setup-example-note"
    >
        {{ overrideNote }}
    </p>

    <div class="sw-composition-setup-example__actions">
        <button
            type="button"
            data-testid="sw-composition-setup-example-increment"
            @click="increment"
        >
            {{ incrementLabel }}
        </button>

        <button
            type="button"
            data-testid="sw-composition-setup-example-reset"
            @click="reset"
        >
            Reset
        </button>
    </div>
</section>
</template>

<script setup lang="ts" sw-component="sw-composition-setup-example">
import { computed, ref } from 'vue';

const count = ref(2);
const privateClicks = ref(0);
const headline = ref('Composition setup example');
const overrideNote = ref('Base component only');

const doubled = computed(() => count.value * 2);
const incrementLabel = computed(() => `Increment to ${count.value + 1}`);

function increment(): void {
    count.value += 1;
    privateClicks.value += 1;
}

function reset(): void {
    count.value = 2;
    privateClicks.value = 0;
}

swDefinePublic({
    count,
    doubled,
    headline,
});
</script>

<style scoped>
.sw-composition-setup-example {
    display: grid;
    gap: 12px;
    max-width: 520px;
    padding: 20px;
    border: 1px solid #d1d9e0;
    border-radius: 4px;
    background: #fff;
}

.sw-composition-setup-example__headline {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.sw-composition-setup-example__state {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin: 0;
}

.sw-composition-setup-example__state div {
    padding: 12px;
    background: #f5f7fa;
    border-radius: 4px;
}

.sw-composition-setup-example__state dt {
    margin-bottom: 4px;
    color: #52667a;
    font-size: 12px;
}

.sw-composition-setup-example__state dd {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.sw-composition-setup-example__note {
    margin: 0;
    color: #52667a;
}

.sw-composition-setup-example__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
</style>

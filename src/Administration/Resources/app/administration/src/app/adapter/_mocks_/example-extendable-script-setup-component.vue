<template>
    <div>
        <div class="base">Base: {{ baseValue }}</div>
        <div class="multiplier">Multiplier: {{ multiplier }}</div>
        <div class="multiplied">Multiplied: {{ multipliedValue }}</div>
        <div class="addedValue">Added value: {{ addedValue }}</div>
        <div class="added">Added: {{ added }}</div>
        <div class="deep">Deep: {{ reactiveValue.very.deep.value }}</div>
        <div class="private">Private: {{ privateStuff }}</div>
        <div class="message">Message: {{ message }}</div>
        <button class="increment" @click="increment">Increment</button>
    </div>
</template>

<script setup lang="ts">
import {createExtendableSetup} from "../composition-extension-system";
import {computed, reactive, ref} from "vue";

const props = defineProps({
    multiplier: {
        type: Number,
        default: 1,
    },
    added: {
        type: Number,
        default: 0,
    },
});

const {
    baseValue,
    multipliedValue,
    addedValue,
    reactiveValue,
    increment,
    privateStuff,
    message,
} = createExtendableSetup(
    {
        props,
        name: 'originalComponent',
    },
    () => {
        const baseValue = ref(1);
        const reactiveValue = reactive({
            very: {
                deep: {
                    value: 'deep',
                }
            }
        })
        const multipliedValue = computed(() => baseValue.value * props.multiplier);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        const addedValue = computed(() => baseValue.value + props.added);

        const increment = () => {
            baseValue.value++;
        }

        const privateStuff = ref('Very private stuff')

        const message = ref('Original message')

        return {
            private: {
                privateStuff,
            },
            public: {
                baseValue,
                multipliedValue,
                addedValue,
                reactiveValue,
                increment,
                message,
            },
        };
    },
)
</script>

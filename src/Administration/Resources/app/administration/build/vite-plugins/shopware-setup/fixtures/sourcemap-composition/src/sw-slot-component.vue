<script setup>
import { defineComponent, h } from 'vue';
const Receiver = defineComponent({
    setup(_, { slots }) {
        return () => h('div', slots.content?.());
    },
});
swDefinePublic({});
</script>
<template>
    <Receiver
        ><sw-block name="slot-content"><template #content>Slot source marker</template></sw-block></Receiver
    >
</template>

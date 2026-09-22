<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const modelValue = computed<string>({
    get: () => previousState.modelValue.value ?? '',
    set: (next: string) => {
        previousState.modelValue.value = next.toUpperCase();
    },
});

swDefineOverride({
    modelValue,
});
</script>
